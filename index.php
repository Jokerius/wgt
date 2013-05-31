<?php
define('IMAGE_WIDTH', 1600);
define('IMAGE_HEIGHT', 600);
define('SHOW_MEDIAN_AVERAGE', 0);
define('SHOW_MEDIAN_LAST5', TRUE);

$width = IMAGE_WIDTH;
$height = IMAGE_HEIGHT;

global $width, $height, $offset_w, $offset_h;

$offset_w = round(0.03*$width);
$offset_h = round(0.05*$height);
$width = round(0.94*$width);
$height = round(0.9*$height);

/*
 * Get data array from DB
 * Default all items if `last` not set
 */
function get_data(){
    include_once 'config.php';
    global $CONFIG;
    try{
        $db = new PDO('mysql:host='.$CONFIG['dbhost'].';dbname='.$CONFIG['dbname'].';charset=utf8', $CONFIG['dbuser'], $CONFIG['dbpass']);
        
        if(intval($_GET['last']>0)){
            $stmt = $db->query("SELECT date, weight FROM graph order by date DESC limit ".intval($_GET['last']));
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result = array_reverse($result);
        }else{
            $stmt = $db->query("SELECT date, weight FROM graph order by date ASC");
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }catch(PDOException $e){
        var_dump($e);
    }

    return $result;
}

/*
 * Random data for test
 */
function get_test_data(){
    $result = array();
    $date = new DateTime('2013-01-01');
    for($i=0; $i<20; $i++){
    
        $date->add(new DateInterval('P1D'));
    
        $result[] = array(
            'date' => $date->format('Y-m-d') ,
            'weight' => mt_rand(800, 920)/10
        );
    }

    return $result;
}

/*
 * Processing data, creating array of points and building image
 */
function build_image(){
    $width = IMAGE_WIDTH;
    $height = IMAGE_HEIGHT;
    $im = imagecreatetruecolor($width, $height);
    
    global $width, $height, $offset_w, $offset_h;
     
    $background = imagecolorallocate($im, 10, 10, 10);
    imagefilledrectangle($im , 0, 0, IMAGE_WIDTH, IMAGE_HEIGHT , $background );

    $data = get_data();

    $min_date = $data[0]['date'];
    $max_date = $data[sizeof($data)-1]['date'];
    

    $min_w = $data[0]['weight'];
    $max_w = $data[0]['weight'];
    foreach($data as $row){
        if($row['weight']>$max_w){
            $max_w = $row['weight'];
        }
        if($row['weight']<$min_w){
            $min_w = $row['weight'];
        }
        
        $total += $row['weight'];
        $now = $row['date'];

        $fix_dates[] = (strtotime($now)-strtotime($min_date))/86400;
    }
    
    $average = $total / sizeof($data);
    
    for($i=0;$i<sizeof($data);$i++){
        $data[$i]{'fix_date'} = $fix_dates[$i];
    }
    
    build_grid($data[sizeof($data)-1]['fix_date'], round(($max_w - $min_w)*10), $im);
    
    draw_average_line($average, $max_w, $min_w, $im);

    $points = array();
    foreach($data as $row){
        $points[] = array(
            'x' => round($row['fix_date'] * $width / ((strtotime($max_date) - strtotime($min_date))/86400)) + $offset_w,
            'y' => $height - round(($row['weight']-$min_w) * $height / ($max_w - $min_w)) + $offset_h
        );

    }
    build_graphic($points, $data, $im);

    // Output and free from memory
    header('Content-Type: image/png');
    imagepng($im);
    imagedestroy($im);
}

/*
 * Builds graphic 
 */
function build_graphic($points, $data, $im){
    global $width, $height, $offset_w, $offset_h;
    
    $color = imagecolorallocate($im, 0, 255, 100);
    $gross = imagecolorallocate($im, 0, 100, 255);
    $med = imagecolorallocate($im, 150, 100, 200);            
    $distance = 500;
    for($i=0;$i<sizeof($points);$i++){       
        imagerectangle($im, $points[$i]['x']-2, $points[$i]['y']-2, $points[$i]['x']+2, $points[$i]['y']+2, $color);
        imagestring($im, 2, $points[$i]['x']-30 , $points[$i]['y'], $data[$i]['weight'] ,$color);
        
        //date in the bottom

        if($distance > 200){
            imageline($im, $points[$i]['x'], $height+$offset_h, $points[$i]['x'], $height+$offset_h+10, $color);
            imagestring($im, 2, $points[$i]['x']-30 , $height+$offset_h + 12, $data[$i]['date'] ,$color);
            $distance = 0;
        }
        
        $distance += $points[$i+1]['x']-$points[$i]['x'];
        
        if(isset($points[$i+1])){
            imageline($im, $points[$i]['x'], $points[$i]['y'], $points[$i+1]['x'], $points[$i+1]['y'], $color);

            $sum += $points[$i]['y'];
            
            if(SHOW_MEDIAN_AVERAGE ){
                imageline($im, $points[$i]['x'], $sum/($i+1), $points[$i+1]['x'], ($sum+$points[$i+1]['y'])/($i+2), $gross);
                imagerectangle($im, $points[$i]['x']-2, $sum/($i+1)-2, $points[$i]['x']+2, $sum/($i+1)+2, $gross);
                
            }
            
            if(SHOW_MEDIAN_LAST5){
                //$y1 = ($points[$i]['y']+$points[$i-1]['y']+$points[$i-2]['y']+$points[$i-3]['y']+$points[$i-4]['y'])/5;
                //$y2 = ($points[$i+1]['y']+$points[$i]['y']+$points[$i-1]['y']+$points[$i-2]['y']+$points[$i-3]['y'])/5;
                $j=0;
                $sum1 = 0;
                $sum2 = 0;
                while(isset($points[$i-$j]['y']) && $j<4){
                  $sum1 += $points[$i-$j]['y'];
                  $sum2 += $points[$i-$j+1]['y'];                    
                  $j++;
                  $y1 = $sum1 / ($j);
                  $y2 = $sum2 / ($j);

                }
                
                if(!isset($points[$i-3])){
                    $sum2 += $points[$i-$j+1]['y'];                    
                    $j++;
                    $y2 = $sum2 / ($j);
                }
                imageline($im, $points[$i]['x'], $y1, $points[$i+1]['x'], $y2, $med);
                imagerectangle($im, $points[$i]['x']-2, $y1-2, $points[$i]['x']+2, $y1+2, $med);                
            }
        }        
    }
}

/*
 * Draws red line to show average value
 */
function draw_average_line($average, $max_w, $min_w, $im){
    
    global $width, $height, $offset_w, $offset_h;
    $red = imagecolorallocate($im, 220, 0, 200);
    $y = $height - ($average - $min_w) * $height / ($max_w - $min_w) + $offset_h;
    imageline($im, $offset_w, $y, $offset_w + $width, $y, $red);    
    imagestring($im, 2, $offset_w+$width+10, $y, round($average,2), $red);
}

/*
 * Builds grid by days and 0.1 values
 */
function build_grid($rows, $columns, $image){

    global $width, $height, $offset_w, $offset_h;
    
    $grid_color = imagecolorallocate($image, 90, 90, 90);
    $grid_light_color = imagecolorallocate($image, 120, 120, 120);
    // vertical grid
    for($i=0;$i<$rows+1;$i++){
        $x = $offset_w + $i/$rows*$width;
        if($i%10==0){
            imageline($image, $x, $offset_h, $x, $offset_h+$height, $grid_light_color);
        }else{
            imageline($image, $x, $offset_h, $x, $offset_h+$height, $grid_color);
        }
    }
    
    for($i=0;$i<$columns+1;$i++){
        $y = $offset_h + $i/$columns*$height;
        imageline($image, $offset_w, $y, $offset_w+$width, $y, $grid_color);
    }
}

build_image();