import React from "react";
import { View, Text, TouchableOpacity } from "react-native";
import { dashboardStyles as styles } from "../styles/Stylesheet";

export default function DashboardScreen({ navigation }) {
  const handleLogout = () => {
    const parentNavigation = navigation.getParent();

    if (parentNavigation) {
      parentNavigation.reset({
        index: 0,
        routes: [{ name: "Login" }]
      });
      return;
    }

    navigation.navigate("Login");
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity style={styles.logoutButton} onPress={handleLogout}>
          <Text style={styles.logoutButtonText}>Cerrar sesión</Text>
        </TouchableOpacity>
      </View>

      <View style={styles.welcomeCard}>
        <Text style={styles.welcomeTitle}>Hola, Usuario</Text>
        <Text style={styles.welcomeMessage}>
          Se han abierto nuevas vacantes a las que eres elegible. Revisa la sección de Vacantes para más detalles.
        </Text>
      </View>
    </View>
  );
}
