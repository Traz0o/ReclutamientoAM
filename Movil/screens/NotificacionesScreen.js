import { View, Text } from "react-native";
import { notificacionesStyles as styles } from "../styles/Stylesheet";

export default function NotificacionesScreen(){

  return(
    <View style={styles.container}>

      <View style={styles.notification}>
        <Text>Nueva vacante disponible</Text>
      </View>

      <View style={styles.notification}>
        <Text>Tu solicitud fue recibida</Text>
      </View>

    </View>
  )
}

