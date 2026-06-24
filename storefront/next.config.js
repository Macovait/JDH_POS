/** @type {import('next').NextConfig} */
const nextConfig = {
  // The PHP API lives at a different origin in dev
  async rewrites() {
    const phpBase = 'http://localhost/JDH_POS/public';
    return [
      // API
      {
        source: '/api/v1/:path*',
        destination: `${phpBase}/api/v1/:path*`,
      },
      // Store logo (outside public/ folder)
      {
        source: '/uploads/store/:path*',
        destination: `http://localhost/JDH_POS/uploads/store/:path*`,
      },
      // Product, category & banner images (inside public/ folder)
      {
        source: '/uploads/:path*',
        destination: `${phpBase}/uploads/:path*`,
      },
    ];
  },
  images: {
    remotePatterns: [
      { protocol: 'http',  hostname: 'localhost' },
      { protocol: 'https', hostname: '**' },
    ],
  },
  async headers() {
    return [
      {
        source: '/:path*',
        headers: [
          // Allow PHP customizer to iframe the storefront (cross-port localhost)
          { key: 'X-Frame-Options', value: 'SAMEORIGIN' },
          { key: 'Content-Security-Policy', value: "frame-ancestors 'self' http://localhost http://localhost:80 http://localhost:3000 http://127.0.0.1 http://127.0.0.1:80 http://127.0.0.1:3000" },
        ],
      },
    ];
  },
  env: {
    PHP_API_URL: process.env.PHP_API_URL || 'http://localhost/JDH_POS/public/api/v1',
    NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL || '/api/v1',
  },
};

module.exports = nextConfig;
