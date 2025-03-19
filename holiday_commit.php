<?php
// 📌 Función para cargar variables desde .env
function loadEnv($path) {
    if (!file_exists($path)) {
        die("❌ Error: No se encontró el archivo .env\n");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Ignorar comentarios
        }

        list($key, $value) = explode('=', $line, 2);
        putenv("$key=$value");
    }
}

// 📌 Cargar variables de entorno desde .env
loadEnv(__DIR__ . '/.env');

// 📌 Obtener credenciales desde el entorno
$github_token = getenv('GITHUB_TOKEN');
$api_key = getenv('ACCESS_TOKEN');

if (!$github_token) {
    die("❌ Error: No se encontró el token de GitHub en las variables de entorno.\n");
}
if (!$api_key) {
    die("❌ Error: No se encontró el token de la API en las variables de entorno.\n");
}

$github_repo = "Rafaelx-ss/Holidays-in-the-world";

// Configurar UTF-8 para Git
putenv('LC_ALL=en_US.UTF-8');

echo "🔄 Obteniendo fecha actual...\n";
$year = "2024";
$month = date("m");
$day = date("d");
echo "📅 Fecha actual: $year-$month-$day\n";

// 📌 Obtener lista de países
echo "🌍 Obteniendo lista de países...\n";
$country_list_url = "https://holidayapi.com/v1/countries?key=$api_key";
$country_list_json = file_get_contents($country_list_url);
$country_list = json_decode($country_list_json, true);

if (!isset($country_list['countries'])) {
    die("❌ Error: No se pudo obtener la lista de países. Verifica tu API Key.\n");
}

// 📌 Mezclar la lista de países y buscar un festivo en hasta 50 intentos
shuffle($country_list['countries']);

$found = false;
$attempts = 0;
$max_attempts = 50;
$days_offset = 0;  // 📅 Si no se encuentra un festivo, buscar en fechas futuras

while (!$found && $attempts < $max_attempts) {
    $random_country = $country_list['countries'][array_rand($country_list['countries'])];
    $country_code = $random_country['code'];

    // 📅 Si no encuentra festivos, buscar en los próximos 5 días
    $current_day = date("d", strtotime("+$days_offset days"));

    echo "🎲 Intento #$attempts - Consultando festivos en: $random_country[name] ($country_code) para el día $current_day\n";

    // URL para obtener los festivos
    $holiday_url = "https://holidayapi.com/v1/holidays?key=$api_key&country=$country_code&year=$year&month=$month&day=$current_day&language=es";
    echo "🌍 URL consultada: $holiday_url\n";

    $holiday_json = @file_get_contents($holiday_url);
    
    if ($holiday_json === false) {
        echo "❌ Error al consultar la API. Verifica tu clave de API.\n";
        die();
    }

    $holiday_response = json_decode($holiday_json, true);

    if (!empty($holiday_response['holidays'])) {
        $holiday = $holiday_response['holidays'][array_rand($holiday_response['holidays'])];
        $holiday_name = $holiday['name'];
        $holiday_country = $holiday['country'];
        $found = true;
        echo "✅ Festivo encontrado en intento #$attempts: $holiday_name en $holiday_country\n";
    } else {
        echo "❌ No se encontró festivo en $random_country[name]. Intentando otro país...\n";
        $attempts++;

        // 📅 Si después de 25 intentos no encuentra nada, probar con otro día (máx. 5 días adelante)
        if ($attempts % 25 == 0) {
            $days_offset++;
            echo "🔄 No se encontraron festivos, probando con la fecha +$days_offset días...\n";
            if ($days_offset > 5) {
                break; // 🔴 Si ya probó 5 días adelante y no hay festivos, detener.
            }
        }
    }
}

// 📌 Si no se encontró ningún festivo, colocar mensaje genérico
if (!$found) {
    $holiday_name = "No hay festivos registrados en la base de datos.";
    $holiday_country = "N/A";
    echo "❌ No se encontró un festivo después de $max_attempts intentos.\n";
}

// 📌 ACTUALIZAR `README.md`
echo "✍️ Actualizando README.md...\n";

// ⚠️ FORZAR CAMBIO: Eliminar y reescribir `README.md`
if (file_exists("README.md")) {
    unlink("README.md"); // Elimina el archivo para garantizar que Git detecte el cambio
}

$readme_template = "## 🌍 Holidays in the World - Festivos en el Mundo 🎉\n\n";
$readme_template .= "📅 Fecha: $year-$month-$day\n";
$readme_template .= "🌍 País: $holiday_country\n";
$readme_template .= "🎉 Festivo: $holiday_name\n";
file_put_contents("README.md", $readme_template);

echo "✅ README.md actualizado localmente.\n";

// 📌 Hacer Pull antes de subir cambios
echo "🔄 Haciendo pull de los últimos cambios...\n";
exec("git pull origin main 2>&1", $git_output);
echo implode("\n", $git_output) . "\n";

// 📌 Verificar cambios con `git status`
echo "🔎 Verificando si hay cambios en Git...\n";
exec("git status --porcelain", $output);
if (!empty($output)) {
    echo "📌 Se detectaron cambios en Git. Procediendo con el commit...\n";

    // 📌 Mostrar archivos modificados antes de hacer commit
    echo "📋 Archivos modificados:\n";
    exec("git status -s", $status_output);
    echo implode("\n", $status_output) . "\n";

    exec("git add -A");
    exec("git commit -m \"Actualización automática - $year-$month-$day\" 2>&1", $commit_output);
    echo implode("\n", $commit_output) . "\n";

    // 📌 Subir cambios a GitHub
    echo "🚀 Subiendo cambios a GitHub...\n";
    exec("git push https://$github_token@github.com/$github_repo.git 2>&1", $push_output);
    echo implode("\n", $push_output) . "\n";

    echo "✅ README.md actualizado y subido correctamente.\n";
} else {
    echo "⚠️ No hubo cambios en el festivo. No se realizó commit.\n";
}
?>
