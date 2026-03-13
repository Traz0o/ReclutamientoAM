import { StyleSheet } from "react-native";
import colors from "../constants/colors";

export const loginStyles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
    justifyContent: "center",
    padding: 25
  },
  title: {
    fontSize: 28,
    fontWeight: "bold",
    color: colors.primary,
    textAlign: "center",
    marginBottom: 40
  },
  input: {
    backgroundColor: colors.white,
    padding: 15,
    borderRadius: 10,
    marginBottom: 15
  },
  button: {
    backgroundColor: colors.secondary,
    padding: 15,
    borderRadius: 10,
    alignItems: "center",
    marginTop: 10
  },
  buttonText: {
    color: colors.white,
    fontSize: 16,
    fontWeight: "bold"
  }
});

export const dashboardStyles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
    paddingHorizontal: 20,
    paddingTop: 20
  },
  header: {
    flexDirection: "row",
    justifyContent: "flex-end",
    alignItems: "center"
  },
  title: {
    fontSize: 24,
    fontWeight: "bold",
    color: colors.primary
  },
  logoutButton: {
    backgroundColor: colors.white,
    borderWidth: 1,
    borderColor: colors.secondary,
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 8
  },
  logoutButtonText: {
    color: colors.secondary,
    fontSize: 14,
    fontWeight: "bold"
  },
  welcomeCard: {
    marginTop: 24,
    backgroundColor: colors.white,
    borderRadius: 12,
    padding: 18
  },
  welcomeTitle: {
    fontSize: 22,
    fontWeight: "bold",
    color: colors.primary,
    marginBottom: 8
  },
  welcomeMessage: {
    fontSize: 15,
    color: colors.text,
    lineHeight: 22
  }
});

export const vacantesStyles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
    padding: 15
  },
  card: {
    backgroundColor: colors.white,
    padding: 20,
    borderRadius: 10,
    marginBottom: 15,
    elevation: 3
  },
  puesto: {
    fontSize: 18,
    fontWeight: "bold",
    color: colors.primary
  },
  empresa: {
    fontSize: 14,
    color: colors.text
  },
  infoBox: {
    backgroundColor: colors.white,
    borderRadius: 10,
    padding: 14,
    marginBottom: 14
  },
  infoText: {
    color: colors.text,
    fontSize: 14,
    fontWeight: "600"
  }
});

export const notificacionesStyles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
    padding: 15
  },
  notification: {
    backgroundColor: colors.white,
    padding: 20,
    borderRadius: 10,
    marginBottom: 10
  }
});

export const vacanteStyles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
    padding: 20
  },
  titulo: {
    fontSize: 24,
    fontWeight: "bold",
    color: colors.primary,
    marginBottom: 10
  },
  empresa: {
    fontSize: 16,
    marginBottom: 20
  },
  seccion: {
    fontSize: 18,
    fontWeight: "bold",
    marginTop: 10
  },
  texto: {
    fontSize: 14,
    marginTop: 5
  },
  boton: {
    marginTop: 30,
    backgroundColor: colors.secondary,
    padding: 15,
    borderRadius: 10,
    alignItems: "center"
  },
  botonTexto: {
    color: colors.white,
    fontWeight: "bold"
  }
});