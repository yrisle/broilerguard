<?php
// weather_functions.php
// Configuration
define('WEATHER_API_KEY', '00e058de0e83480e9c853401262307');
define('WEATHER_CITY', 'Nasugbu');

function getWeatherData() {
    $cacheFile = 'weather_cache.json';
    $cacheTime = 1800; // 30 minuto

    // Subukan munang basahin ang cache
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
        return json_decode(file_get_contents($cacheFile), true);
    }

    // 1. Kunin ang kasalukuyang panahon (current.json)
    $currentUrl = "https://api.weatherapi.com/v1/current.json?key=" . WEATHER_API_KEY . "&q=" . WEATHER_CITY . "&aqi=no";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $currentUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $currentResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$currentResponse) {
        // Kapag may error, gamitin ang lumang cache (kung mayroon)
        if (file_exists($cacheFile)) {
            return json_decode(file_get_contents($cacheFile), true);
        }
        // Fallback values
        return [
            'temp' => 31, 'feels_like' => 31, 'temp_min' => 28, 'temp_max' => 33,
            'humidity' => 70, 'pressure' => 1013, 'condition' => 'Partly Cloudy',
            'description' => 'Partly Cloudy', 'icon_code' => '//cdn.weatherapi.com/weather/64x64/day/116.png',
            'wind_speed' => 10, 'wind_deg' => 180, 'city' => WEATHER_CITY,
            'country' => 'Philippines', 'sunrise' => '06:00 AM', 'sunset' => '06:00 PM',
            'last_updated' => date('h:i A')
        ];
    }

    $currentData = json_decode($currentResponse, true);

    // 2. Kunin ang forecast para sa min/max at sunrise/sunset
    $forecastUrl = "https://api.weatherapi.com/v1/forecast.json?key=" . WEATHER_API_KEY . "&q=" . WEATHER_CITY . "&days=1&aqi=no&alerts=no";
    
    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, $forecastUrl);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
    $forecastResponse = curl_exec($ch2);
    $forecastHttpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    $forecastData = ($forecastHttpCode === 200 && $forecastResponse) ? json_decode($forecastResponse, true) : null;

    // Buuin ang array
    $weatherData = [
        'temp'        => round($currentData['current']['temp_c']),
        'feels_like'  => round($currentData['current']['feelslike_c']),
        'humidity'    => $currentData['current']['humidity'],
        'pressure'    => $currentData['current']['pressure_mb'],
        'condition'   => $currentData['current']['condition']['text'],
        'description' => $currentData['current']['condition']['text'],
        'icon_code'   => $currentData['current']['condition']['icon'], // URL ng icon
        'wind_speed'  => $currentData['current']['wind_kph'],
        'wind_deg'    => $currentData['current']['wind_degree'],
        'city'        => $currentData['location']['name'],
        'country'     => $currentData['location']['country'],
        'last_updated'=> date('h:i A', strtotime($currentData['current']['last_updated'])),
        'temp_min'    => $forecastData ? round($forecastData['forecast']['forecastday'][0]['day']['mintemp_c']) : round($currentData['current']['temp_c']),
        'temp_max'    => $forecastData ? round($forecastData['forecast']['forecastday'][0]['day']['maxtemp_c']) : round($currentData['current']['temp_c']),
        'sunrise'     => $forecastData ? $forecastData['forecast']['forecastday'][0]['astro']['sunrise'] : '--',
        'sunset'      => $forecastData ? $forecastData['forecast']['forecastday'][0]['astro']['sunset'] : '--',
    ];

    // I-cache ang resulta
    file_put_contents($cacheFile, json_encode($weatherData));
    return $weatherData;
}

function getWeatherIcon($conditionText) {
    // Simple mapping batay sa text ng kondisyon
    $map = [
        'Sunny' => 'fa-sun', 'Clear' => 'fa-sun',
        'Partly cloudy' => 'fa-cloud-sun', 'Cloudy' => 'fa-cloud',
        'Overcast' => 'fa-cloud', 'Mist' => 'fa-smog',
        'Fog' => 'fa-smog', 'Rain' => 'fa-cloud-rain',
        'Light rain' => 'fa-cloud-rain', 'Moderate rain' => 'fa-cloud-rain',
        'Heavy rain' => 'fa-cloud-showers-heavy', 'Thunderstorm' => 'fa-bolt',
        'Snow' => 'fa-snowflake', 'Hail' => 'fa-icicles',
    ];
    foreach ($map as $key => $icon) {
        if (stripos($conditionText, $key) !== false) {
            return $icon;
        }
    }
    return 'fa-cloud';
}
?>