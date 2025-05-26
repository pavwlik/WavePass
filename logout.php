<?php
session_start();
session_destroy();
header("Location: /WavePass/index.php");
exit();
?>