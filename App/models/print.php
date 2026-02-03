<?php

    function FetchPrintDetail($conn) {
        $stmt = $conn->query("SELECT * FROM tw_printer LIMIT 10");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


?>