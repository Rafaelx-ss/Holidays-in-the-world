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

// 📌 Obtener el token de GitHub desde la variable de entorno
$github_token = getenv('GITHUB_TOKEN');
if (!$github_token) {
    die("❌ Error: No se encontró el token de GitHub en las variables de entorno.\n");
}


$api_key = getenv('ACCESS_TOKEN');
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

// Obtener lista de países
echo "🌍 Obteniendo lista de países...\n";
$country_list_url = "https://holidayapi.com/v1/countries?key=$api_key";
$country_list_json = file_get_contents($country_list_url);
$country_list = json_decode($country_list_json, true);

// Verificar si hay países en la respuesta
if (!isset($country_list['countries'])) {
    die("❌ Error: No se pudo obtener la lista de países.\n");
}

// Mezclar la lista de países y buscar un festivo en hasta 10 intentos
shuffle($country_list['countries']);

$found = false;
$attempts = 0;
$max_attempts = 10;

while (!$found && $attempts < $max_attempts) {
    $random_country = $country_list['countries'][array_rand($country_list['countries'])];
    $country_code = $random_country['code'];
    
    echo "🎲 Intento #$attempts - Consultando festivos en: $random_country[name] ($country_code)\n";

    // URL para obtener los festivos
    $holiday_url = "https://holidayapi.com/v1/holidays?key=$api_key&country=$country_code&year=$year&month=$month&day=$day&language=es";
    $holiday_json = file_get_contents($holiday_url);
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
    }
}

if (!$found) {
    $holiday_name = "No hay festivos en ningún país hoy.";
    $holiday_country = "N/A";
    echo "❌ No se encontró un festivo después de $max_attempts intentos.\n";
}

// 📌 ACTUALIZAR `README.md`
echo "✍️ Actualizando README.md...\n";
$readme_template = file_get_contents("README.md");
$readme_updated = str_replace(["{{DATE}}", "{{COUNTRY}}", "{{HOLIDAY_NAME}}"], 
                              ["$year-$month-$day", "$holiday_country", "$holiday_name"], 
                              $readme_template);
file_put_contents("README.md", $readme_updated);
echo "✅ README.md actualizado localmente.\n";

// 📌 VERIFICAR SI HUBO CAMBIOS ANTES DE HACER COMMIT
echo "🔄 Haciendo pull de los últimos cambios...\n";
exec("git pull origin main 2>&1", $git_output);
echo implode("\n", $git_output) . "\n";

// Verificar si hay cambios antes de hacer commit
echo "🔎 Verificando si hay cambios en Git...\n";
exec("git status --porcelain", $output);
if (!empty($output)) {
    echo "📌 Se detectaron cambios en Git. Procediendo con el commit...\n";
    
    exec("git add -A");  // 🔹 Asegura que todos los archivos sean agregados a Git
    exec("git commit -m \"Actualizacion automatica - $year-$month-$day\" 2>&1", $commit_output);
    echo implode("\n", $commit_output) . "\n";

    echo "🚀 Subiendo cambios a GitHub...\n";
    exec("git push https://$github_token@github.com/$github_repo.git 2>&1", $push_output);
    echo implode("\n", $push_output) . "\n";

    echo "✅ README.md actualizado y subido correctamente.\n";
} else {
    echo "⚠️ No hubo cambios en el festivo. No se realizó commit.\n";
}
?>
