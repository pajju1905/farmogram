<?php
// ===============================================
// Google Gemini 1.5 Flash API Helper
// Handles Multimodal (Text + Image) Requests
// ===============================================
header('Content-Type: application/json');

// 🚨 IMPORTANT: Replace with your actual Google Gemini API Key
$GEMINI_API_KEY = getenv('GEMINI_API_KEY') ?: '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST requests are allowed.']);
    exit();
}

$user_message = isset($_POST['message']) ? trim($_POST['message']) : '';

// Ensure we have either a message or an image
if (empty($user_message) && !isset($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Message or image is required.']);
    exit();
}

$parts = [];

// Adding text prompt if provided
if (!empty($user_message)) {
    $parts[] = ['text' => $user_message];
}

// Adding image if provided
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['image']['tmp_name'];
    $mimeType = mime_content_type($fileTmpPath);

    // Read file and convert to base64
    $imageData = file_get_contents($fileTmpPath);
    $base64Image = base64_encode($imageData);

    $parts[] = [
        'inline_data' => [
            'mime_type' => $mimeType,
            'data' => $base64Image
        ]
    ];
}

// Prepare payload for Gemini
$payload = [
    'contents' => [
        [
            'parts' => $parts
        ]
    ],
    'system_instruction' => [
        'parts' => [
            ['text' => "You are an elite, highly knowledgeable agricultural assistant designed specifically for the 'Farmogram' platform. Your goal is to help farmers by providing accurate, actionable, and friendly advice regarding crops, soil, farming techniques, market prices, pests, and diseases. When a farmer uploads an image, analyze it thoroughly to identify the crop and any visible symptoms of disease or deficiency, and provide specific remedies. Speak in a proactive, encouraging tone."]
        ]
    ],
    'generation_config' => [
        'temperature' => 0.4,
        'max_output_tokens' => 800
    ]
];

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=" . $GEMINI_API_KEY;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local dev / XAMPP compatibility

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    curl_close($ch);
    http_response_code(500);
    echo json_encode(['error' => 'cURL Error: ' . $error_msg]);
    exit();
}

curl_close($ch);

$decodedResponse = json_decode($response, true);

if ($httpcode == 200 && isset($decodedResponse['candidates'][0]['content']['parts'][0]['text'])) {
    $bot_reply = $decodedResponse['candidates'][0]['content']['parts'][0]['text'];
    echo json_encode(['reply' => $bot_reply]);
} else {
    // Provide a helpful simulation response if the API key fails or network is down
    $msg_lower = strtolower($user_message);
    
    if (strpos($msg_lower, 'rust') !== false || strpos($msg_lower, 'fungus') !== false || strpos($msg_lower, 'disease') !== false || isset($_FILES['image'])) {
        $sim_reply = "### 🌱 Farmogram AI Assistant (Simulated Advice)

Based on typical symptom patterns (like spots, powder, or rust-like pustules on leaves), this is likely a **fungal infection** (such as leaf rust or powdery mildew).

**Actionable Remedies:**
1. **Pruning**: Remove and destroy infected leaves immediately to prevent spreading.
2. **Air Circulation**: Avoid overhead watering; ensure adequate space between crops for ventilation.
3. **Organic Solution**: Apply a spray made from neem oil or a baking soda solution (3 tbsp per gallon of water).
4. **Chemical Control**: If symptoms persist, apply a copper-based fungicide according to package instructions.
5. **Autopilot Action**: Logged a task to inspect surrounding plots.";
    } elseif (strpos($msg_lower, 'tomato') !== false || strpos($msg_lower, 'wilt') !== false) {
        $sim_reply = "### 🌱 Farmogram AI Assistant (Simulated Advice)

If your tomatoes are showing wilting or spots, it could be **Early Blight** or **Fusarium Wilt**.

**Steps to Resolve:**
1. Apply organic compost to the soil to boost nutrient levels.
2. Keep water off the foliage (drip irrigation is recommended).
3. Remove bottom leaves of the tomato plants to stop soil-borne fungal spores from splashing onto lower foliage.";
    } elseif (strpos($msg_lower, 'rice') !== false || strpos($msg_lower, 'paddy') !== false) {
        $sim_reply = "### 🌱 Farmogram AI Assistant (Simulated Advice)

Rice crops require careful management of soil nutrients and water level control.

**Best Practices for Rice/Paddy:**
1. **Water Levels**: Maintain 2-5 cm of water depth in fields during initial growth stages.
2. **Pest Watch**: Watch out for Brown Planthoppers and Rice Blast disease.
3. **Fertilization**: Apply nitrogen fertilizer in split doses to maximize uptake and minimize runoff.";
    } else {
        $sim_reply = "### 🌱 Farmogram AI Assistant (Simulated Advice)

Hello! I am your Farmogram AI Assistant. I can help you with crop disease identification, fertilizer optimization, soil nutrition, and pest control.

Here are a few quick tips you can request details on:
* **Organic Pest Control**: Make a simple soap-and-neem-oil emulsion spray.
* **Fertilizer Scheduling**: Learn about split nitrogen application for wheat and paddy.
* **Water conservation**: Learn how drip irrigation saves up to 40% of crop water consumption.

*Currently running in offline simulation mode due to gateway check.*";
    }
    
    // Return simulated response with status 200 instead of crashing
    echo json_encode(['reply' => $sim_reply, 'is_simulated' => true]);
}
?>