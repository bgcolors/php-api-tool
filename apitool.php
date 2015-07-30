<?php error_reporting(E_ERROR) ?>
<?php 

list($url, $method, $names, $values) = array_map(function($v){
  return empty($_GET[$v]) ? '' : $_GET[$v];
},array('url', 'method', 'names', 'values'));

// echo $url,$method,$names,$values;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>API Tool - made by bg</title>
    <link rel="stylesheet" href="//cdn.bootcss.com/bootstrap/3.3.5/css/bootstrap.min.css">
</head>
<body>

<div class="container">
  <h3>API TOOL</h3>
  <form method="get">
    <div class="form-group">
      <label for="url">URL</label>
      <input type="text" class="form-control" id="url" placeholder="URL" name="url" value="<?php echo empty($url) ? '' : $url; ?>">
    </div> 
    <div class="form-group">
      <label for="method">Method</label>
      <select name="method" class="form-control" id="method">
        <option value="1" <?php echo empty($_GET['method']) || $_GET['method'] != 2 ? 'selected="true"' : '' ?>>GET</option>
        <option value="2" <?php echo !empty($_GET['method']) && $_GET['method'] == 2 ? 'selected="true"' : '' ?>>POST</option>
      </select>
    </div>
    <div class="form-group">
      <label for="names">Parameter Names</label>
      <textarea class="form-control" rows="5" name="names" id="names"><?php echo empty($names) ? '' : $names; ?></textarea>
    </div>
    <div class="form-group">
      <label for="values">Parameter Values</label>
      <textarea class="form-control" rows="5" name="values" id="values"><?php echo empty($values) ? '' : $values; ?></textarea>
    </div>
    <div class="form-group">
      <button type="submit" class="btn btn-default">Send</button>
    </div>
<?php 
$res = '';
if ($url) {
  $namesArr = explode("\r\n", $names);
  $valuesArr = explode("\r\n", $values);
  $params = [];
  $l = count($namesArr);

  for ($i = 0;$i < $l;$i++) {
    if (empty($namesArr[$i])) continue;
    $params[$namesArr[$i]] = empty($valuesArr[$i]) ? '' : $valuesArr[$i];
  }

  // print_r($params);

  switch ($method) {
    case '':
      break;
    case 1;
      try {
        $res = curl_get($url, $params);
      } catch (Exception $e) {
        $fail = true;
      }
      break;
    case 2;
      try {
        $res = curl_post($url, $params);
      } catch (Exception $e) {
        $fail = true;
      }
      break;
    default:
      # code...
      break;
  }
}
 ?>
    <div class="form-group">
      <label for="res">Result</label>
      <textarea class="form-control" rows="15" id="res"><?php echo empty($fail) ? $res : ''?></textarea>
    </div>
    <div class="form-group<?php echo empty($url) || !empty($fail) || empty($res) ? ' has-error' : '';?>">
      <label for="res">error</label>
      <textarea class="form-control" rows="3" id="res"><?php 
      echo empty($url) ? "url can not be empty.\n" : '';
      echo empty($fail) ? '' : "fail to get contents.\n";
      echo empty($res) ? 'no return.' : ''; ?></textarea>
    </div>
  </form>
</div>
    
</body>
</html>
<?php 

/** 
* Send a POST requst using cURL 
* @param string $url to request 
* @param array $post values to send 
* @param array $options for cURL 
* @return string 
*/ 
function curl_post($url, array $post = NULL, array $options = array()) 
{ 
    $defaults = array( 
        CURLOPT_POST => 1, 
        CURLOPT_HEADER => 0, 
        CURLOPT_URL => $url, 
        CURLOPT_FRESH_CONNECT => 1, 
        CURLOPT_RETURNTRANSFER => 1, 
        CURLOPT_FORBID_REUSE => 1, 
        CURLOPT_TIMEOUT => 10, 
        CURLOPT_POSTFIELDS => http_build_query($post) 
    ); 

    $ch = curl_init(); 
    curl_setopt_array($ch, ($options + $defaults)); 
    if( ! $result = curl_exec($ch)) 
    { 
        trigger_error(curl_error($ch)); 
    } 
    curl_close($ch); 
    return $result; 
} 

/** 
* Send a GET requst using cURL 
* @param string $url to request 
* @param array $get values to send 
* @param array $options for cURL 
* @return string 
*/ 
function curl_get($url, array $get = NULL, array $options = array()) 
{    
    $defaults = array( 
        CURLOPT_URL => $url. (strpos($url, '?') === FALSE ? '?' : ''). http_build_query($get), 
        CURLOPT_HEADER => 0, 
        CURLOPT_RETURNTRANSFER => TRUE, 
        CURLOPT_TIMEOUT => 10 
    ); 
    
    $ch = curl_init(); 
    curl_setopt_array($ch, ($options + $defaults)); 
    if( ! $result = curl_exec($ch)) 
    { 
        trigger_error(curl_error($ch)); 
    } 
    curl_close($ch); 
    return $result; 
} 
?>