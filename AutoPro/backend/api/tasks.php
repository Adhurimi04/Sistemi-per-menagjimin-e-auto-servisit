$resendApiKey = "re_XQALHH4A_KcGsBkHaFvTbLeXnjcu4EHdJ"; // Merret falas në resend.com

$data = [
    "from" => "Auto Pro <onboarding@resend.dev>",
    "to" => ["adhurimmehmetii2006@gmail.com"],
    "subject" => "Auto Pro - Detyrë e re Servisi #{$new_task_id}",
    "html" => "<p>Është krijuar një detyrë e re: <strong>{$title}</strong> për veturën me ID <strong>{$vehicle_id}</strong>.</p>"
];

$ch = curl_init("https://api.resend.com/emails");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $resendApiKey,
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);
