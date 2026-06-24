import React from 'react';
import {createStackNavigator} from '@react-navigation/stack';
import {createBottomTabNavigator} from '@react-navigation/bottom-tabs';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';

// Screens
import {LoginScreen} from '@screens/LoginScreen';
import {PosScreen} from '@screens/PosScreen';
import {ProductsScreen} from '@screens/ProductsScreen';
import {SalesScreen} from '@screens/SalesScreen';
import {ReportsScreen} from '@screens/ReportsScreen';
import {SettingsScreen} from '@screens/SettingsScreen';
import {ProductDetailScreen} from '@screens/ProductDetailScreen';
import {SaleDetailScreen} from '@screens/SaleDetailScreen';
import {CheckoutScreen} from '@screens/CheckoutScreen';
import {ReceiptScreen} from '@screens/ReceiptScreen';

// Hooks
import {useAuth} from '@hooks/useAuth';
import {useTheme} from '@hooks/useTheme';

const Stack = createStackNavigator();
const Tab = createBottomTabNavigator();

function MainTabs(): JSX.Element {
  const {theme} = useTheme();

  return (
    <Tab.Navigator
      screenOptions={({route}) => ({
        tabBarIcon: ({focused, color, size}) => {
          let iconName: string;

          switch (route.name) {
            case 'POS':
              iconName = focused ? 'point-of-sale' : 'point-of-sale';
              break;
            case 'Products':
              iconName = focused ? 'package-variant' : 'package-variant-closed';
              break;
            case 'Sales':
              iconName = focused ? 'receipt-text' : 'receipt-text-outline';
              break;
            case 'Reports':
              iconName = focused ? 'chart-box' : 'chart-box-outline';
              break;
            case 'Settings':
              iconName = focused ? 'cog' : 'cog-outline';
              break;
            default:
              iconName = 'help-circle';
          }

          return <Icon name={iconName} size={size} color={color} />;
        },
        tabBarActiveTintColor: theme.colors.primary,
        tabBarInactiveTintColor: theme.colors.textSecondary,
        tabBarStyle: {
          backgroundColor: theme.colors.surface,
          borderTopWidth: 0,
          elevation: 8,
          shadowColor: '#000',
          shadowOffset: {width: 0, height: -2},
          shadowOpacity: 0.1,
          shadowRadius: 4,
        },
        headerShown: false,
      })}>
      <Tab.Screen name="POS" component={PosScreen} />
      <Tab.Screen name="Products" component={ProductsScreen} />
      <Tab.Screen name="Sales" component={SalesScreen} />
      <Tab.Screen name="Reports" component={ReportsScreen} />
      <Tab.Screen name="Settings" component={SettingsScreen} />
    </Tab.Navigator>
  );
}

export function AppNavigator(): JSX.Element {
  const {isAuthenticated, isLoading} = useAuth();
  const {theme} = useTheme();

  if (isLoading) {
    return (
      <Stack.Navigator screenOptions={{headerShown: false}}>
        <Stack.Screen name="Splash">
          {() => (
            <LoadingScreen />
          )}
        </Stack.Screen>
      </Stack.Navigator>
    );
  }

  return (
    <Stack.Navigator
      screenOptions={{
        headerStyle: {
          backgroundColor: theme.colors.surface,
          elevation: 0,
          shadowOpacity: 0,
        },
        headerTintColor: theme.colors.text,
        headerTitleStyle: {
          fontWeight: '600',
        },
        cardStyle: {backgroundColor: theme.colors.background},
      }}>
      {!isAuthenticated ? (
        <Stack.Screen
          name="Login"
          component={LoginScreen}
          options={{headerShown: false}}
        />
      ) : (
        <>
          <Stack.Screen
            name="Main"
            component={MainTabs}
            options={{headerShown: false}}
          />
          <Stack.Screen
            name="ProductDetail"
            component={ProductDetailScreen}
            options={{title: 'Product Details'}}
          />
          <Stack.Screen
            name="SaleDetail"
            component={SaleDetailScreen}
            options={{title: 'Sale Details'}}
          />
          <Stack.Screen
            name="Checkout"
            component={CheckoutScreen}
            options={{
              title: 'Checkout',
              presentation: 'modal',
            }}
          />
          <Stack.Screen
            name="Receipt"
            component={ReceiptScreen}
            options={{
              title: 'Receipt',
              presentation: 'modal',
              headerShown: false,
            }}
          />
        </>
      )}
    </Stack.Navigator>
  );
}

// Simple loading screen component
function LoadingScreen(): JSX.Element {
  return (
    <View style={{flex: 1, justifyContent: 'center', alignItems: 'center'}}>
      <ActivityIndicator size="large" color="#3B82F6" />
    </View>
  );
}

import {View, ActivityIndicator} from 'react-native';
