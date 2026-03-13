import { createBottomTabNavigator } from "@react-navigation/bottom-tabs";

import Dashboard from "../screens/DashboardScreen";
import Vacantes from "../screens/VacantesScreen";
import Notificaciones from "../screens/NotificacionesScreen";

const Tab = createBottomTabNavigator();

export default function TabNavigator(){
  return(
    <Tab.Navigator>
      <Tab.Screen name="Dashboard" component={Dashboard} />
      <Tab.Screen name="Vacantes" component={Vacantes} />
      <Tab.Screen name="Notificaciones" component={Notificaciones} />
    </Tab.Navigator>
  )
}