import { View, Text, TouchableOpacity } from "react-native";
import { vacanteStyles as styles } from "../styles/Stylesheet";

export default function VacanteScreen({ navigation, route }) {
  const vacante = route?.params?.vacante;
  const puesto = vacante?.puesto ?? "Vacante";
  const empresa = vacante?.empresa ?? "Empresa no especificada";
  const descripcion =
    vacante?.descripcion ?? "No hay descripción disponible para esta vacante.";
  const requisitos = vacante?.requisitos ?? ["No hay requisitos registrados"];

  return (
    <View style={styles.container}>

      <Text style={styles.titulo}>{puesto}</Text>

      <Text style={styles.empresa}>Empresa: {empresa}</Text>

      <Text style={styles.seccion}>Descripción</Text>
      <Text style={styles.texto}>{descripcion}</Text>

      <Text style={styles.seccion}>Requisitos</Text>
      <Text style={styles.texto}>{requisitos.map((item) => `- ${item}`).join("\n")}</Text>

      <TouchableOpacity
        style={styles.boton}
        onPress={() => navigation.goBack()}
      >
        <Text style={styles.botonTexto}>Volver a vacantes</Text>
      </TouchableOpacity>

    </View>
  );
}