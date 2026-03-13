import { View, Text, FlatList, TouchableOpacity } from "react-native";
import { vacantesStyles as styles } from "../styles/Stylesheet";

const vacantes = [
    {
        id: 1,
        puesto: "Gerente de Calidad",
        empresa: "Brose",
        descripcion:
            "Responsable de garantizar el cumplimiento de estándares de calidad en los sistemas mecatrónicos fabricados por la empresa.",
        requisitos: [
            "Ingeniería Industrial / Mecánica / Mecatrónica",
            "Experiencia en industria automotriz",
            "Conocimiento en IATF 16949"
        ]
    },
    {
        id: 2,
        puesto: "Ingeniero de Producción",
        empresa: "Brose",
        descripcion:
            "Asegura la eficiencia de líneas de producción, mejora procesos y cumplimiento de objetivos de manufactura.",
        requisitos: [
            "Ingeniería Mecánica / Mecatrónica / Industrial",
            "Manejo de indicadores KPI",
            "Experiencia en mejora continua"
        ]
    }
];

export default function VacantesScreen({ navigation }){

    const renderItem = ({item}) => (
    <TouchableOpacity
        style={styles.card}
        onPress={() => navigation.navigate("Vacante", { vacante: item })}
    >
        <Text style={styles.puesto}>{item.puesto}</Text>
        <Text style={styles.empresa}>{item.empresa}</Text>
    </TouchableOpacity>
    );

    return(
        <View style={styles.container}>
            <View style={styles.infoBox}>
                <Text style={styles.infoText}>
                    Eres elegible para alguna de las siguientes vacantes.
                </Text>
            </View>
            <FlatList
                data={vacantes}
                renderItem={renderItem}
                keyExtractor={(item)=>item.id.toString()}
        />
        </View>
    )
}

