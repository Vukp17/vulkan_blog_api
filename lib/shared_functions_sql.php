<?php 

    /**
     * Insert new event to log_event
     * 
     * "id_event"	"description"
     *   "0"	"Nedoločen"
     *   "1"	"Prijava"
     *   "2"	"Odjava"
     *   "3"	"Brisanje"
     *   "4"	"Dodajanje"
     *   "5"	"Urejanje"
     *   "6"	"Tiskanje"
     *   "7"	"Predogled"
     *   "8"	"Nastavitve"
     *   "9"	"Pregledi"
     *   "10"	"Analiza"
     *   "11"	"Šifrant"
     *   "12"	"Shranjevanje"
     *   "13"	"Storno"
     *   "14"	"Brisanje postavke"
     *   "15"	"Dodajanje postavke"
     *   "16"	"Urejanje postavke"
     *   "17"	"Pošiljanje e-pošte"
     *   "18"	"Odpiranje modula"
     * 
     * @param dbo PDO instance
     * @param userId Id of user who made event
     * @param id_dok Id of item that was on event
     * @param id_event Id of event that happend
     * @param forma String where event happend
     * @param time When event happend -> null = now
     */
    function createLogEvent(PDO $dbc, string $userId, string $id_dok, int $id_event, string $forma, ?string $time = null): void{
        if($time == null) $time = date("Y-m-d H:i:s");
        // Create NEW Event
        $query="INSERT INTO log_event
            ( dt_log, inicialke, dok_id, event_id, forma)
            VALUES
            ( :dt_log, :inicialke, :id_dok, :id_event, :forma)"; 
        $stmt = $dbc->prepare($query);
        $stmt->bindValue(":dt_log",$time,PDO::PARAM_STR);
        $stmt->bindValue(":inicialke",$userId,PDO::PARAM_STR);
        $stmt->bindValue(":id_dok",$id_dok,PDO::PARAM_STR);
        $stmt->bindValue(":id_event",$id_event,PDO::PARAM_INT);
        $stmt->bindValue(":forma",$forma,PDO::PARAM_STR);
        $stmt->execute();
    }

?>