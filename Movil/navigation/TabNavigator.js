import { createBottomTabNavigator } from "@react-navigation/bottom-tabs";
import Dashboard from "../screens/DashboardScreen";
import Vacantes from "../screens/VacantesScreen";
import Notificaciones from "../screens/NotificacionesScreen";

const Tab = createBottomTabNavigator();

export default function TabNavigator({ route }) {
  const { token, nombre, id_empleado } = route.params ?? {};
  return (
    <Tab.Navigator>
      <Tab.Screen name="Dashboard" component={Dashboard} initialParams={{ nombre }} />
      <Tab.Screen name="Vacantes" component={Vacantes} initialParams={{ token, id_empleado }} />
      <Tab.Screen name="Notificaciones" component={Notificaciones} />
    </Tab.Navigator>
  );
}