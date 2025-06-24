<?php
putenv("GIT_CONFIG_PARAMETERS='core.threads=1 gc.auto=0 pack.threads=1 pack.windowMemory=5m pack.packSizeLimit=10m'");

// 📌 Función para convertir código de país a emoji de bandera
function countryCodeToEmoji($countryCode)
{
    if ($countryCode == "N/A") {
        return "🏳"; // Bandera blanca si no hay país
    }

    $emoji = "";
    foreach (str_split(strtoupper($countryCode)) as $letter) {
        $emoji .= mb_chr(ord($letter) + 127397);
    }
    return $emoji;
}

echo "🔄 Iniciando script... \n";


// 📌 Obtener credenciales desde el entorno
$github_token = getenv('GITHUB_TOKEN');
$api_key = getenv('ACCESS_TOKEN');


if (!$github_token) {
    echo "❌ No se encontró el token de GitHub.\n";
    die("❌ Error: No se encontró el token de GitHub en las variables de entorno.\n");
}
if (!$api_key) {
    echo "❌ No se encontró el token de la API.\n";
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
$country_list_url = "https://holidayapi.com/v1/countries?pretty&key=$api_key";


$country_list_json = file_get_contents($country_list_url); // Esto hace que se guarde en un archivo temporal
$country_list = json_decode($country_list_json, true); // Decodifica el JSON en un array asociativo

if (!isset($country_list['countries'])) {
    echo "❌ No se pudo obtener la lista de países.\n";
    die("❌ Error: No se pudo obtener la lista de países. Verifica tu API Key.\n");
}

$country_list = $country_list['countries'];
// echo json_encode($country_list);
shuffle($country_list);

$found = false;
$attempts = 0;
$max_attempts = 100;
$days_offset = 0;  // 📅 Si no se encuentra un festivo, buscar en fechas futuras
$country_list_count = count($country_list);

while (!$found && $attempts < $max_attempts) {
    $random_country = $country_list[array_rand($country_list)];
    $country_code = $random_country['code'];
    $country_name = $random_country['name'];
    $holiday_flag_png = $random_country['flag'];

    // 📅 Si no encuentra festivos, buscar en los próximos 5 días
    $current_day = date("d", strtotime("+$days_offset days"));

    echo "🎲 Intento #$attempts - Consultando festivos en: {$random_country['name']} ($country_code) para el día $current_day\n";

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
        $holiday_country_code = $holiday['country'];
        $holiday_country = $country_name;
        $holiday_flag = countryCodeToEmoji($holiday_country_code);
        $holiday_flag_png = $random_country['flag'];

        $found = true;
        echo "✅ Festivo encontrado en intento #$attempts: $holiday_name en $holiday_country\n";
    } else {
        echo "❌ No se encontró festivo en {$country_name}. ($country_code) Intentando otro país...\n";
        $attempts++;

        // 📅 Si de recorrer toda la lista de países aumentar 1 día (máx. 5 días adelante)
        
        if ($country_list_count == $attempts) {
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
    $holiday_country_code = "N/A";
    $holiday_flag = "🏳";
    $holiday_flag_png = "";
    echo "❌ No se encontró un festivo después de $max_attempts intentos.\n";
}

// 📌 ACTUALIZAR `README.md`
echo "✍️ Actualizando README.md...\n";

// ⚠️ FORZAR CAMBIO: Eliminar y reescribir `README.md`
if (file_exists("README.md")) {
    unlink("README.md"); // Elimina el archivo para garantizar que Git detecte el cambio
}

$year = date("Y");
$month = date("m");
$day = date("d");

if($day % 1 == 0){
    $space = ' ';
}else{
    $space = '';
}

$readme_template = <<<EOT
    # 🌍 Holidays in the World - Festivos en el Mundo 🎉
    
    > Un script automatizado que obtiene y actualiza diariamente los **días festivos del mundo** en español.  
    > Cada día, busca un festivo en cualquier país y lo sube automáticamente a este repositorio.  
    
    ---
    
    ## 📅 Último Festivo Encontrado
    > ✅ **Fecha:** `$year-$month-$day`  
    > 🌍 **País:** `$holiday_country $holiday_flag ($holiday_country_code)`  
    > 🎉 **Festivo:** `$holiday_name`  
    > 🔰 **Bandera:**
    > 
    > <img src="$holiday_flag_png" height="100px" alt="$holiday_country_code ?? $holiday_country">
    
    *(Este dato se actualiza diariamente con un commit automático.)*
    
    ---
    
    ## 🚀 ¿Cómo Funciona?
    - Se obtiene la fecha actual del **año**.
    - Se consulta la **[Holiday API](https://holidayapi.com/)** en busca de un festivo en cualquier país.
    - Se actualiza el archivo `README.md` con la información más reciente.
    - Se realiza automáticamente un **commit y push** a este repositorio.
    
    ---
    📝 *Este proyecto es parte de una automatización para registrar festividades globales.*  
    🌟 **¡No olvides dar ⭐️ al repo si te gusta!** 🚀
    $space
EOT;



// 👉 Forzar cambio en el README con un comentario timestamp oculto
$readme_template .= "\n\n<!-- Actualizado automáticamente el " . date('Y-m-d H:i:s') . " -->";

file_put_contents("README.md", $readme_template);

echo "✅ README.md actualizado localmente.\n";

// 👉 Establecer configuración para limitar hilos por recursos del servidor
putenv("GIT_CONFIG_PARAMETERS='core.threads=1'");

// 📌 Hacer fetch de los últimos cambios del repo remoto
echo "🔄 Verificando si el repo remoto tiene cambios...\n";


// 📌 Verificar si hay cambios en el working directory
echo "🔎 Verificando si hay cambios en Git...\n";
exec("git status --porcelain", $output);

if (!empty($output)) {
    echo "📌 Se detectaron cambios en Git. Procediendo con el commit...\n";
    
    exec('git config user.name "Rafaelx-ss"');
    exec('git config user.email "solisrafael895@gmail.com"');
    exec("git add -A");
    exec("git commit -m \"Update holiday - $year-$month-$day\" 2>&1", $commit_output);

    echo implode("\n", $commit_output) . "\n";
    echo "Commit:\n" . implode("\n", $commit_output) . "\n";


    // 📌 Subir cambios a GitHub
    echo "🚀 Subiendo cambios a GitHub...\n";
    
    exec("git config --local pack.threads 1");
    exec("git config --local pack.windowMemory 10m");
    exec("git config --local pack.packSizeLimit 20m");
    
    $gc_log = __DIR__ . '/.git/gc.log';
    if (file_exists($gc_log)) {
        unlink($gc_log);
    }

    exec("git remote set-url origin https://$github_token@github.com/$github_repo.git");

    exec("git push --force origin main 2>&1", $push_output);
    echo implode("\n", $push_output) . "\n";

    echo "✅ README.md actualizado y subido correctamente.\n";
    echo "Push:\n" . implode("\n", $push_output) . "\n";
    echo "✅ Push completado.\n";
} else {
    echo "⚠️ No hubo cambios en el festivo. No se realizó commit.\n";
}
echo "✅ Script finalizado.\n";
