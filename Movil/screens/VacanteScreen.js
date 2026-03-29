import { useState, useEffect } from "react";
import { View, Text, ScrollView, TouchableOpacity, ActivityIndicator, Alert } from "react-native";
import { vacanteStyles as styles } from "../styles/Stylesheet";

const API_URL = "http://192.168.100.9:8000/api";

export default function VacanteScreen({ navigation, route }) {
  const { vacante: vacanteResumen, token } = route.params ?? {};
  console.log("vacanteResumen completo:", JSON.stringify(vacanteResumen));
  const [vacante, setVacante] = useState(null);
  const [loading, setLoading] = useState(true);
  const [respondiendo, setRespondiendo] = useState(false);

  useEffect(() => {
    fetch(`${API_URL}/vacantes/${vacanteResumen.id_vacante}`, {
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
      },
    })
      .then((res) => res.json())
      .then((data) => {
        setVacante(data);
        setLoading(false);
      })
      .catch(() => setLoading(false));
  }, []);

  const responder = async (acepta) => {
    console.log("id_postulacion:", vacanteResumen.id_postulacion);
    setRespondiendo(true);
    try {
      const response = await fetch(`${API_URL}/postulaciones/${vacanteResumen.id_postulacion}/respuesta-empleado`, {
        method: "PATCH",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ acepta }),
      });
      const data = await response.json();
      if (response.ok) {
        Alert.alert(
          acepta ? "Postulación aceptada" : "Postulación rechazada",
          acepta ? "Has aceptado esta vacante." : "Has rechazado esta vacante.",
          [{ text: "OK", onPress: () => navigation.goBack() }]
        );
      } else {
        Alert.alert("Error", data.message ?? "No se pudo procesar.");
      }
    } catch {
      Alert.alert("Error", "No se pudo conectar al servidor.");
    } finally {
      setRespondiendo(false);
    }
  };

  if (loading) return <ActivityIndicator style={{ flex: 1 }} size="large" />;

  return (
    <ScrollView style={styles.container}>
      <Text style={styles.titulo}>{vacante?.titulo ?? "Vacante"}</Text>
      <Text style={styles.empresa}>Área: {vacante?.nombre_area ?? "—"}</Text>
      <Text style={styles.empresa}>Salario: {vacante?.salario ?? "—"}</Text>
      <Text style={styles.empresa}>Fecha límite: {vacante?.fecha_cierre ?? "—"}</Text>

      <Text style={styles.seccion}>Descripción</Text>
      <Text style={styles.texto}>{vacante?.descripcion ?? "Sin descripción."}</Text>

      <Text style={styles.seccion}>Requisitos</Text>
      {(vacante?.requisitos ?? []).map((req, i) => (
        <Text key={i} style={styles.texto}>
          • {req.descripcion} ({req.nombre_tipo}) — Mínimo: {req.valor_minimo ?? "—"} | Ideal: {req.valor_ideal ?? "—"}
        </Text>
      ))}

      <TouchableOpacity
        style={[styles.boton, { backgroundColor: "#16a618", marginTop: 20 }]}
        onPress={() => responder(true)}
        disabled={respondiendo}
      >
        <Text style={styles.botonTexto}>Aceptar postulación</Text>
      </TouchableOpacity>

      <TouchableOpacity
        style={[styles.boton, { backgroundColor: "#dc2626", marginTop: 10 }]}
        onPress={() => responder(false)}
        disabled={respondiendo}
      >
        <Text style={styles.botonTexto}>Rechazar postulación</Text>
      </TouchableOpacity>

      <TouchableOpacity style={[styles.boton, { marginTop: 10, marginBottom: 30 }]} onPress={() => navigation.goBack()}>
        <Text style={styles.botonTexto}>Volver a vacantes</Text>
      </TouchableOpacity>
    </ScrollView>
  );
}