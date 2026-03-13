import { NavigationContainer } from "@react-navigation/native";
import { createNativeStackNavigator } from "@react-navigation/native-stack";

import Login from "./screens/LoginScreen";
import TabNavigator from "./navigation/TabNavigator";
import VacanteScreen from "./screens/VacanteScreen";

const Stack = createNativeStackNavigator();

export default function App() {
  return (
    <NavigationContainer>
      <Stack.Navigator>
        <Stack.Screen name="Login" component={Login} />
        <Stack.Screen
          name="Main"
          component={TabNavigator}
          options={{ headerShown:false }}
        />
        <Stack.Screen
          name="Vacante"
          component={VacanteScreen}
          options={{ title: "Detalle de vacante" }}
        />
      </Stack.Navigator>
    </NavigationContainer>
  );
}