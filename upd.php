
    <form action="upd.php" method="POST">
    <input type="date" name="date"/>
    <input type="text" name="value"/>
    <input type="password" name="order"/>
    <input type="submit" name="submit" value="ok"/>
    </form>
        <?php

include_once 'config.php';
global $CONFIG;  
if($_POST['submit']=='ok' && $_POST['order']==$CONFIG['upd_pass']){  
    try{
        $db = new PDO('mysql:host='.$CONFIG['dbhost'].';dbname='.$CONFIG['dbname'].';charset=utf8', $CONFIG['dbuser'], $CONFIG['dbpass']);
        $stmt = $db->prepare("insert into graph values (NULL, ?, ?)");
        $stmt->execute( array($_POST['date'], $_POST['value']));
        sleep(1.5);
        echo 'added';
    }catch(PDOException $e){
        var_dump($e);
    }
}



?>