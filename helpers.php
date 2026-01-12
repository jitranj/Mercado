<?php
function xss($data) {
    if ($data === null) return "";
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}
?>