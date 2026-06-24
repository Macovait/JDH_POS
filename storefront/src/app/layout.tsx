import type { Metadata } from 'next';
import { Inter, Playfair_Display, Poppins } from 'next/font/google';
import { Toaster } from 'react-hot-toast';
import './globals.css';

// Font configurations
const inter = Inter({ 
  subsets: ['latin'],
  variable: '--font-inter',
  display: 'swap',
});

const playfair = Playfair_Display({
  subsets: ['latin'],
  variable: '--font-playfair',
  display: 'swap',
});

const poppins = Poppins({
  weight: ['300', '400', '500', '600', '700', '800'],
  subsets: ['latin'],
  variable: '--font-poppins',
  display: 'swap',
});

// Default metadata
export const metadata: Metadata = {
  title: {
    template: '%s | Online Store',
    default: 'Online Store — Shop the Best Products',
  },
  description: 'Discover amazing products at great prices. Shop online with fast delivery and secure payment.',
  keywords: ['online store', 'shop', 'products', 'ecommerce', 'shopping'],
  authors: [{ name: 'Online Store' }],
  creator: 'Online Store',
  publisher: 'Online Store',
  robots: {
    index: true,
    follow: true,
    googleBot: {
      index: true,
      follow: true,
      'max-video-preview': -1,
      'max-image-preview': 'large',
      'max-snippet': -1,
    },
  },
  openGraph: {
    type: 'website',
    locale: 'en_US',
    url: 'https://store.example.com',
    siteName: 'Online Store',
    title: 'Online Store — Shop the Best Products',
    description: 'Discover amazing products at great prices. Shop online with fast delivery and secure payment.',
    images: [
      {
        url: 'https://store.example.com/og-image.jpg',
        width: 1200,
        height: 630,
        alt: 'Online Store',
      },
    ],
  },
  twitter: {
    card: 'summary_large_image',
    title: 'Online Store — Shop the Best Products',
    description: 'Discover amazing products at great prices. Shop online with fast delivery and secure payment.',
    images: ['https://store.example.com/twitter-image.jpg'],
    creator: '@onlinestore',
    site: '@onlinestore',
  },
  viewport: {
    width: 'device-width',
    initialScale: 1,
    maximumScale: 5,
  },
  themeColor: [
    { media: '(prefers-color-scheme: light)', color: '#ffffff' },
    { media: '(prefers-color-scheme: dark)', color: '#0b1120' },
  ],
  manifest: '/manifest.json',
  icons: {
    icon: [
      { url: '/favicon.ico' },
      { url: '/favicon-16x16.png', sizes: '16x16', type: 'image/png' },
      { url: '/favicon-32x32.png', sizes: '32x32', type: 'image/png' },
    ],
    apple: [
      { url: '/apple-touch-icon.png' },
    ],
  },
  verification: {
    google: 'google-site-verification-code',
    yandex: 'yandex-verification-code',
  },
  category: 'ecommerce',
};

// Props type
interface RootLayoutProps {
  children: React.ReactNode;
}

export default function RootLayout({ children }: RootLayoutProps) {
  return (
    <html 
      lang="en" 
      className={`${inter.variable} ${playfair.variable} ${poppins.variable}`}
      suppressHydrationWarning
    >
      <head>
        {/* Preconnect to external resources */}
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        
        {/* DNS prefetch for critical domains */}
        <link rel="dns-prefetch" href="//cdnjs.cloudflare.com" />
        <link rel="dns-prefetch" href="//fonts.googleapis.com" />
        
        {/* Fallback for non-JS environments */}
        <noscript>
          <style>{`
            .no-js-hidden {
              display: none !important;
            }
            .no-js-block {
              display: block !important;
            }
          `}</style>
        </noscript>
      </head>
      <body 
        className={`${inter.className} antialiased bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 min-h-screen`}
        suppressHydrationWarning
      >
        {/* Skip to main content - Accessibility */}
        <a
          href="#main-content"
          className="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:top-4 focus:left-4 focus:bg-white dark:focus:bg-slate-800 focus:text-slate-900 dark:focus:text-white focus:px-4 focus:py-2 focus:rounded-lg focus:shadow-lg"
        >
          Skip to main content
        </a>

        {/* Main Content */}
        <main id="main-content" className="min-h-screen">
          {children}
        </main>

        {/* Toast Notifications */}
        <Toaster
          position="top-right"
          toastOptions={{
            duration: 4000,
            style: {
              background: '#1e293b',
              color: '#f1f5f9',
              borderRadius: '0.75rem',
              padding: '1rem',
              boxShadow: '0 10px 40px rgba(0, 0, 0, 0.4)',
              border: '1px solid rgba(51, 65, 85, 0.6)',
            },
            success: {
              iconTheme: {
                primary: '#10b981',
                secondary: '#ffffff',
              },
            },
            error: {
              iconTheme: {
                primary: '#ef4444',
                secondary: '#ffffff',
              },
            },
            loading: {
              style: {
                background: '#1e293b',
                color: '#f1f5f9',
              },
            },
          }}
        />

        {/* Theme Script - Prevent flash of wrong theme */}
        <script
          dangerouslySetInnerHTML={{
            __html: `
              (function() {
                try {
                  const theme = localStorage.getItem('theme');
                  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                  if (theme === 'dark' || (!theme && prefersDark)) {
                    document.documentElement.classList.add('dark');
                  } else {
                    document.documentElement.classList.remove('dark');
                  }
                } catch (e) {}
              })();
            `,
          }}
        />

        {/* Performance Monitoring (optional) */}
        {process.env.NODE_ENV === 'production' && (
          <script
            dangerouslySetInnerHTML={{
              __html: `
                // Production performance monitoring
                if (window.performance) {
                  window.addEventListener('load', function() {
                    const perfData = performance.getEntriesByType('navigation')[0];
                    if (perfData) {
                      console.debug('Page load time:', perfData.loadEventEnd - perfData.fetchStart, 'ms');
                    }
                  });
                }
              `,
            }}
          />
        )}
      </body>
    </html>
  );
}

// Enable static generation for metadata routes
export const generateStaticParams = async () => {
  return [];
};

// Revalidate cache every 60 seconds in production
export const revalidate = 60;