import React, {useState, useEffect, useCallback} from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  FlatList,
  TextInput,
  Alert,
  Dimensions,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {useNavigation} from '@react-navigation/native';
import {useTheme} from '@hooks/useTheme';
import {useCart} from '@hooks/useCart';
import {api} from '@services/api';
import {Product, Category, CartItem} from '@types/index';

const {width} = Dimensions.get('window');
const NUM_COLUMNS = width > 600 ? 4 : 3;

export function PosScreen(): JSX.Element {
  const navigation = useNavigation();
  const {theme} = useTheme();
  const {cart, addToCart, removeFromCart, updateQuantity, clearCart, total} = useCart();
  
  const [products, setProducts] = useState<Product[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [selectedCategory, setSelectedCategory] = useState<number | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [loading, setLoading] = useState(true);
  const [showCart, setShowCart] = useState(true);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      setLoading(true);
      const [productsRes, categoriesRes] = await Promise.all([
        api.get('/products'),
        api.get('/categories'),
      ]);
      setProducts(productsRes.data.data);
      setCategories(categoriesRes.data.data);
    } catch (error) {
      Alert.alert('Error', 'Failed to load products');
    } finally {
      setLoading(false);
    }
  };

  const filteredProducts = useCallback(() => {
    let filtered = products;
    
    if (selectedCategory) {
      filtered = filtered.filter(p => p.category_id === selectedCategory);
    }
    
    if (searchQuery) {
      filtered = filtered.filter(p => 
        p.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        p.sku?.toLowerCase().includes(searchQuery.toLowerCase()) ||
        p.barcode?.includes(searchQuery)
      );
    }
    
    return filtered;
  }, [products, selectedCategory, searchQuery]);

  const handleProductPress = (product: Product) => {
    addToCart(product);
  };

  const handleCheckout = () => {
    if (cart.length === 0) {
      Alert.alert('Empty Cart', 'Please add items to cart first');
      return;
    }
    navigation.navigate('Checkout', {cart, total});
  };

  const handleHoldSale = async () => {
    if (cart.length === 0) return;
    
    try {
      await api.post('/pos/held-sales', {
        items: cart,
        total_amount: total,
      });
      clearCart();
      Alert.alert('Success', 'Sale held successfully');
    } catch (error) {
      Alert.alert('Error', 'Failed to hold sale');
    }
  };

  const renderCategory = ({item}: {item: Category}) => (
    <TouchableOpacity
      style={[
        styles.categoryButton,
        {
          backgroundColor: selectedCategory === item.id 
            ? theme.colors.primary 
            : theme.colors.surface,
          borderColor: theme.colors.border,
        },
      ]}
      onPress={() => setSelectedCategory(
        selectedCategory === item.id ? null : item.id
      )}>
      <Icon
        name={item.icon || 'folder'}
        size={24}
        color={selectedCategory === item.id ? '#fff' : theme.colors.text}
      />
      <Text
        style={[
          styles.categoryText,
          {
            color: selectedCategory === item.id ? '#fff' : theme.colors.text,
          },
        ]}>
        {item.name}
      </Text>
    </TouchableOpacity>
  );

  const renderProduct = ({item}: {item: Product}) => (
    <TouchableOpacity
      style={[
        styles.productCard,
        {backgroundColor: theme.colors.surface},
      ]}
      onPress={() => handleProductPress(item)}>
      <View style={styles.productImage}>
        <Icon name="package" size={40} color={theme.colors.primary} />
      </View>
      <Text style={[styles.productName, {color: theme.colors.text}]} numberOfLines={2}>
        {item.name}
      </Text>
      <Text style={[styles.productPrice, {color: theme.colors.primary}]}>
        ${item.price.toFixed(2)}
      </Text>
      {item.quantity <= item.min_stock && (
        <View style={styles.lowStockBadge}>
          <Text style={styles.lowStockText}>Low Stock</Text>
        </View>
      )}
    </TouchableOpacity>
  );

  const renderCartItem = ({item}: {item: CartItem}) => (
    <View style={[styles.cartItem, {backgroundColor: theme.colors.surface}]}>
      <View style={styles.cartItemInfo}>
        <Text style={[styles.cartItemName, {color: theme.colors.text}]}>
          {item.name}
        </Text>
        <Text style={[styles.cartItemPrice, {color: theme.colors.textSecondary}]}>
          ${(item.price * item.quantity).toFixed(2)}
        </Text>
      </View>
      <View style={styles.quantityControls}>
        <TouchableOpacity
          style={[styles.qtyButton, {backgroundColor: theme.colors.primary}]}
          onPress={() => updateQuantity(item.id, item.quantity - 1)}>
          <Icon name="minus" size={16} color="#fff" />
        </TouchableOpacity>
        <Text style={[styles.qtyText, {color: theme.colors.text}]}>
          {item.quantity}
        </Text>
        <TouchableOpacity
          style={[styles.qtyButton, {backgroundColor: theme.colors.primary}]}
          onPress={() => updateQuantity(item.id, item.quantity + 1)}>
          <Icon name="plus" size={16} color="#fff" />
        </TouchableOpacity>
        <TouchableOpacity
          style={styles.removeButton}
          onPress={() => removeFromCart(item.id)}>
          <Icon name="delete" size={20} color="#EF4444" />
        </TouchableOpacity>
      </View>
    </View>
  );

  return (
    <View style={[styles.container, {backgroundColor: theme.colors.background}]}>
      {/* Header */}
      <View style={[styles.header, {backgroundColor: theme.colors.surface}]}>
        <Text style={[styles.headerTitle, {color: theme.colors.text}]}>
          Point of Sale
        </Text>
        <TouchableOpacity
          style={styles.cartToggle}
          onPress={() => setShowCart(!showCart)}>
          <Icon name={showCart ? 'chevron-down' : 'chevron-up'} size={24} color={theme.colors.text} />
          <View style={styles.cartBadge}>
            <Text style={styles.cartBadgeText}>{cart.length}</Text>
          </View>
        </TouchableOpacity>
      </View>

      {/* Search */}
      <View style={[styles.searchContainer, {backgroundColor: theme.colors.surface}]}>
        <Icon name="magnify" size={20} color={theme.colors.textSecondary} />
        <TextInput
          style={[styles.searchInput, {color: theme.colors.text}]}
          placeholder="Search products..."
          placeholderTextColor={theme.colors.textSecondary}
          value={searchQuery}
          onChangeText={setSearchQuery}
        />
        {searchQuery.length > 0 && (
          <TouchableOpacity onPress={() => setSearchQuery('')}>
            <Icon name="close-circle" size={20} color={theme.colors.textSecondary} />
          </TouchableOpacity>
        )}
      </View>

      {/* Categories */}
      {categories.length > 0 && (
        <FlatList
          horizontal
          data={categories}
          renderItem={renderCategory}
          keyExtractor={item => item.id.toString()}
          style={styles.categoryList}
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.categoryContent}
        />
      )}

      {/* Products Grid */}
      <FlatList
        data={filteredProducts()}
        renderItem={renderProduct}
        keyExtractor={item => item.id.toString()}
        numColumns={NUM_COLUMNS}
        contentContainerStyle={styles.productGrid}
        showsVerticalScrollIndicator={false}
      />

      {/* Cart Section */}
      {showCart && cart.length > 0 && (
        <View style={[styles.cartSection, {backgroundColor: theme.colors.surface}]}>
          <View style={styles.cartHeader}>
            <Text style={[styles.cartTitle, {color: theme.colors.text}]}>
              Current Order
            </Text>
            <TouchableOpacity onPress={clearCart}>
              <Text style={{color: '#EF4444'}}>Clear All</Text>
            </TouchableOpacity>
          </View>
          
          <FlatList
            data={cart}
            renderItem={renderCartItem}
            keyExtractor={item => item.id.toString()}
            style={styles.cartList}
          />
          
          <View style={styles.cartFooter}>
            <View style={styles.totalRow}>
              <Text style={[styles.totalLabel, {color: theme.colors.text}]}>
                Total
              </Text>
              <Text style={[styles.totalAmount, {color: theme.colors.primary}]}>
                ${total.toFixed(2)}
              </Text>
            </View>
            
            <View style={styles.actionButtons}>
              <TouchableOpacity
                style={[styles.holdButton, {backgroundColor: '#F59E0B'}]}
                onPress={handleHoldSale}>
                <Icon name="pause" size={20} color="#fff" />
                <Text style={styles.actionButtonText}>Hold</Text>
              </TouchableOpacity>
              
              <TouchableOpacity
                style={[styles.checkoutButton, {backgroundColor: theme.colors.primary}]}
                onPress={handleCheckout}>
                <Icon name="cart-check" size={20} color="#fff" />
                <Text style={styles.actionButtonText}>Checkout</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 1},
    shadowOpacity: 0.1,
    shadowRadius: 2,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '700',
  },
  cartToggle: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  cartBadge: {
    backgroundColor: '#3B82F6',
    borderRadius: 10,
    minWidth: 20,
    height: 20,
    justifyContent: 'center',
    alignItems: 'center',
    marginLeft: 4,
  },
  cartBadgeText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '600',
  },
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    margin: 12,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 12,
  },
  searchInput: {
    flex: 1,
    marginLeft: 8,
    fontSize: 16,
  },
  categoryList: {
    maxHeight: 80,
  },
  categoryContent: {
    paddingHorizontal: 12,
    gap: 8,
  },
  categoryButton: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 25,
    borderWidth: 1,
    marginRight: 8,
  },
  categoryText: {
    marginLeft: 8,
    fontSize: 14,
    fontWeight: '600',
  },
  productGrid: {
    padding: 8,
    gap: 8,
  },
  productCard: {
    flex: 1,
    margin: 4,
    borderRadius: 12,
    padding: 12,
    alignItems: 'center',
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 1},
    shadowOpacity: 0.1,
    shadowRadius: 2,
  },
  productImage: {
    width: 60,
    height: 60,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 8,
  },
  productName: {
    fontSize: 12,
    fontWeight: '600',
    textAlign: 'center',
    marginBottom: 4,
  },
  productPrice: {
    fontSize: 14,
    fontWeight: '700',
  },
  lowStockBadge: {
    position: 'absolute',
    top: 4,
    right: 4,
    backgroundColor: '#EF4444',
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 4,
  },
  lowStockText: {
    color: '#fff',
    fontSize: 8,
    fontWeight: '600',
  },
  cartSection: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    maxHeight: '50%',
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    elevation: 8,
    shadowColor: '#000',
    shadowOffset: {width: 0, height: -4},
    shadowOpacity: 0.1,
    shadowRadius: 8,
  },
  cartHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: 'rgba(0,0,0,0.1)',
  },
  cartTitle: {
    fontSize: 18,
    fontWeight: '700',
  },
  cartList: {
    maxHeight: 200,
  },
  cartItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 12,
    marginHorizontal: 12,
    marginVertical: 4,
    borderRadius: 8,
  },
  cartItemInfo: {
    flex: 1,
  },
  cartItemName: {
    fontSize: 14,
    fontWeight: '600',
    marginBottom: 2,
  },
  cartItemPrice: {
    fontSize: 12,
  },
  quantityControls: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  qtyButton: {
    width: 28,
    height: 28,
    borderRadius: 14,
    justifyContent: 'center',
    alignItems: 'center',
  },
  qtyText: {
    marginHorizontal: 12,
    fontSize: 16,
    fontWeight: '600',
    minWidth: 24,
    textAlign: 'center',
  },
  removeButton: {
    marginLeft: 12,
    padding: 4,
  },
  cartFooter: {
    padding: 16,
    borderTopWidth: 1,
    borderTopColor: 'rgba(0,0,0,0.1)',
  },
  totalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  totalLabel: {
    fontSize: 18,
    fontWeight: '600',
  },
  totalAmount: {
    fontSize: 24,
    fontWeight: '700',
  },
  actionButtons: {
    flexDirection: 'row',
    gap: 12,
  },
  holdButton: {
    flex: 0.4,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 14,
    borderRadius: 12,
    gap: 8,
  },
  checkoutButton: {
    flex: 0.6,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 14,
    borderRadius: 12,
    gap: 8,
  },
  actionButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '700',
  },
});
