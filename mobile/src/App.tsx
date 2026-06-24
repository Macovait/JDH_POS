/**
 * Jakababa POS Mobile App
 * Main entry point for the React Native application
 */

import React, {useEffect} from 'react';
import {StatusBar, useColorScheme} from 'react-native';
import {NavigationContainer} from '@react-navigation/native';
import {SafeAreaProvider} from 'react-native-safe-area-context';
import {GestureHandlerRootView} from 'react-native-gesture-handler';
import {ThemeProvider} from './contexts/ThemeContext';
import {AuthProvider} from './contexts/AuthContext';
import {AppNavigator} from './navigation/AppNavigator';
import {syncManager} from './services/SyncManager';

function App(): JSX.Element {
  const isDarkMode = useColorScheme() === 'dark';

  useEffect(() => {
    // Initialize background sync
    syncManager.initialize();
    
    // Set up periodic sync
    const syncInterval = setInterval(() => {
      syncManager.syncPendingData();
    }, 30000); // Sync every 30 seconds

    return () => {
      clearInterval(syncInterval);
      syncManager.cleanup();
    };
  }, []);

  return (
    <GestureHandlerRootView style={{flex: 1}}>
      <SafeAreaProvider>
        <ThemeProvider>
          <AuthProvider>
            <NavigationContainer>
              <StatusBar
                barStyle={isDarkMode ? 'light-content' : 'dark-content'}
                backgroundColor={isDarkMode ? '#0F172A' : '#FFFFFF'}
              />
              <AppNavigator />
            </NavigationContainer>
          </AuthProvider>
        </ThemeProvider>
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );
}

export default App;
