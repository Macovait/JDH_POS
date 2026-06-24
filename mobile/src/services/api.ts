/**
 * API Service - Axios wrapper for Jakababa POS API
 */

import axios, {AxiosInstance, AxiosRequestConfig, AxiosResponse} from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';

// API Configuration
const API_BASE_URL = 'http://localhost/JDH_POS/public/api/v1'; // Update for production

// Create axios instance
const apiClient: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Request interceptor - Add auth token
apiClient.interceptors.request.use(
  async (config) => {
    try {
      const token = await AsyncStorage.getItem('auth_token');
      const apiKey = await AsyncStorage.getItem('api_key');
      
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      } else if (apiKey) {
        config.headers['X-API-Key'] = apiKey;
      }
      
      // Add tenant ID if available
      const tenantId = await AsyncStorage.getItem('tenant_id');
      if (tenantId) {
        config.headers['X-Tenant-ID'] = tenantId;
      }
    } catch (error) {
      console.error('Error getting auth token:', error);
    }
    
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor - Handle errors
apiClient.interceptors.response.use(
  (response: AxiosResponse) => {
    return response;
  },
  async (error) => {
    const originalRequest = error.config;
    
    // Handle 401 Unauthorized
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true;
      
      try {
        const refreshToken = await AsyncStorage.getItem('refresh_token');
        if (refreshToken) {
          const response = await axios.post(`${API_BASE_URL}/auth/refresh`, {
            refresh_token: refreshToken,
          });
          
          const {token} = response.data;
          await AsyncStorage.setItem('auth_token', token);
          
          // Retry original request with new token
          originalRequest.headers.Authorization = `Bearer ${token}`;
          return apiClient(originalRequest);
        }
      } catch (refreshError) {
        // Clear auth and redirect to login
        await AsyncStorage.multiRemove([
          'auth_token',
          'refresh_token',
          'user',
          'tenant',
        ]);
        
        // Emit logout event
        // EventEmitter.emit('auth:logout');
      }
    }
    
    // Handle network errors
    if (!error.response) {
      error.message = 'Network error. Please check your connection.';
    }
    
    return Promise.reject(error);
  }
);

// API methods
export const api = {
  // GET request
  get: async <T>(url: string, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> => {
    return apiClient.get<T>(url, config);
  },
  
  // POST request
  post: async <T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> => {
    return apiClient.post<T>(url, data, config);
  },
  
  // PUT request
  put: async <T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> => {
    return apiClient.put<T>(url, data, config);
  },
  
  // PATCH request
  patch: async <T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> => {
    return apiClient.patch<T>(url, data, config);
  },
  
  // DELETE request
  delete: async <T>(url: string, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> => {
    return apiClient.delete<T>(url, config);
  },
  
  // File upload
  upload: async <T>(url: string, file: File | FormData, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> => {
    const formData = file instanceof FormData ? file : new FormData();
    if (!(file instanceof FormData)) {
      formData.append('file', file);
    }
    
    return apiClient.post<T>(url, formData, {
      ...config,
      headers: {
        'Content-Type': 'multipart/form-data',
        ...config?.headers,
      },
    });
  },
  
  // Batch request
  batch: async <T>(requests: Array<{url: string; method: string; data?: any}>): Promise<AxiosResponse<T>[]> => {
    return Promise.all(
      requests.map(req => {
        switch (req.method.toUpperCase()) {
          case 'GET':
            return apiClient.get<T>(req.url);
          case 'POST':
            return apiClient.post<T>(req.url, req.data);
          case 'PUT':
            return apiClient.put<T>(req.url, req.data);
          case 'DELETE':
            return apiClient.delete<T>(req.url);
          default:
            return apiClient.get<T>(req.url);
        }
      })
    );
  },
};

// Auth-specific methods
export const authApi = {
  login: async (email: string, password: string, tenantId?: number) => {
    const response = await apiClient.post('/auth/login', {
      email,
      password,
      tenant_id: tenantId,
    });
    
    const {token, refresh_token, user, tenant} = response.data;
    
    // Store auth data
    await AsyncStorage.multiSet([
      ['auth_token', token],
      ['refresh_token', refresh_token],
      ['user', JSON.stringify(user)],
      ['tenant', JSON.stringify(tenant)],
    ]);
    
    return response.data;
  },
  
  logout: async () => {
    try {
      await apiClient.post('/auth/logout');
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      // Clear local storage
      await AsyncStorage.multiRemove([
        'auth_token',
        'refresh_token',
        'user',
        'tenant',
        'api_key',
      ]);
    }
  },
  
  checkAuth: async () => {
    const [token, userStr, tenantStr] = await AsyncStorage.multiGet([
      'auth_token',
      'user',
      'tenant',
    ]);
    
    if (!token[1]) {
      return null;
    }
    
    return {
      token: token[1],
      user: userStr[1] ? JSON.parse(userStr[1]) : null,
      tenant: tenantStr[1] ? JSON.parse(tenantStr[1]) : null,
    };
  },
  
  changeBranch: async (branchId: number) => {
    const response = await apiClient.post('/auth/switch-branch', {branch_id: branchId});
    
    // Update stored user with new branch
    const userStr = await AsyncStorage.getItem('user');
    if (userStr) {
      const user = JSON.parse(userStr);
      user.branch_id = branchId;
      await AsyncStorage.setItem('user', JSON.stringify(user));
    }
    
    return response.data;
  },
};

// POS-specific methods
export const posApi = {
  // Products
  getProducts: async (params?: {
    category_id?: number;
    search?: string;
    page?: number;
    limit?: number;
  }) => {
    return apiClient.get('/products', {params});
  },
  
  getProduct: async (id: number) => {
    return apiClient.get(`/products/${id}`);
  },
  
  // Categories
  getCategories: async () => {
    return apiClient.get('/categories');
  },
  
  // Sales
  createSale: async (saleData: any) => {
    return apiClient.post('/sales', saleData);
  },
  
  getSales: async (params?: {
    start_date?: string;
    end_date?: string;
    status?: string;
    page?: number;
  }) => {
    return apiClient.get('/sales', {params});
  },
  
  getSale: async (id: number) => {
    return apiClient.get(`/sales/${id}`);
  },
  
  // Held Sales
  getHeldSales: async () => {
    return apiClient.get('/pos/held-sales');
  },
  
  holdSale: async (data: any) => {
    return apiClient.post('/pos/held-sales', data);
  },
  
  resumeHeldSale: async (id: number) => {
    return apiClient.post(`/pos/held-sales/${id}/resume`);
  },
  
  deleteHeldSale: async (id: number) => {
    return apiClient.delete(`/pos/held-sales/${id}`);
  },
  
  // Customers
  getCustomers: async (search?: string) => {
    return apiClient.get('/customers', {params: {search}});
  },
  
  createCustomer: async (data: any) => {
    return apiClient.post('/customers', data);
  },
  
  // Reports
  getSalesReport: async (params: {
    period: string;
    start_date?: string;
    end_date?: string;
  }) => {
    return apiClient.get('/reports/sales', {params});
  },
};

export default apiClient;
