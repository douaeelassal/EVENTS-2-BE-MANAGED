<?php
declare(strict_types=1);

class Security {
    
    public static function generateCaptcha(): array {
        $num1 = rand(1, 9);
        $num2 = rand(1, 9);
        $operators = ['+', '-', '*'];
        $operator = $operators[array_rand($operators)];
        
        switch($operator) {
            case '+': $result = $num1 + $num2; break;
            case '-': $result = $num1 - $num2; break;
            case '*': $result = $num1 * $num2; break;
        }
        
        $question = "$num1 $operator $num2 = ?";
        
        $_SESSION['captcha_answer'] = $result;
        
        return ['question' => $question, 'answer' => $result];
    }
    
    public static function validateCaptcha(string $userAnswer): bool {
        // Vérifier si la réponse utilisateur n'est pas vide
        if (empty(trim($userAnswer))) {
            return false;
        }

        $correctAnswer = $_SESSION['captcha_answer'] ?? null;

        // Nettoyer la réponse de l'utilisateur
        $userAnswer = trim($userAnswer);
        $userAnswerInt = (int)$userAnswer;

        // Vérifier si la réponse est numérique et correcte
        if (!is_numeric($userAnswer) || $correctAnswer === null) {
            return false;
        }

        $isValid = $userAnswerInt === $correctAnswer;

        // Nettoyer la session après vérification
        unset($_SESSION['captcha_answer']);

        return $isValid;
    }
    
    public static function sanitizeInput(string $input): string {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
    
    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
    
    public static function generateToken(): string {
        return bin2hex(random_bytes(32));
    }
    
    public static function validateToken(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function verifyRecaptcha($recaptchaResponse): bool {
        if (empty($recaptchaResponse)) {
            return false;
        }

        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret' => SECRET_KEY,
            'response' => $recaptchaResponse,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];

        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        if ($result === false) {
            return false;
        }

        $resultData = json_decode($result, true);

        return isset($resultData['success']) && $resultData['success'] === true;
    }

    public static function getClientIP() {
        $ipSources = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipSources as $source) {
            if (!empty($_SERVER[$source])) {
                $ip = $_SERVER[$source];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'UNKNOWN';
            }
        }

        return 'UNKNOWN';
    }
}
?>
