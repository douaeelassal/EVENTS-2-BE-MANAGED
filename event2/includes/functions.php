<?php
declare(strict_types=1);

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function flash(string $key,string $message): void {
    $_SESSION['flash'][$key]=$message;
}

function getFlash(string $key): ?string {
    return $_SESSION['flash'][$key]??null;
}

function clearFlash(): void {
    unset($_SESSION['flash']);
}
?>
