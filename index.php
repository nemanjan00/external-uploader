<?php
require_once __DIR__ . '/vendor/autoload.php';

if(getenv("DATABASE_URL") != ""){
	$url = parse_url(getenv("DATABASE_URL"));

	$config = array(
		'driver' => 'mysql', 
		'host' => $url["host"], 
		'database' => substr($url["path"], 1), 
		'username' => $url["user"], 
		'password' => $url["pass"], 
		'charset' => 'utf8', 
		'collation' => 'utf8_unicode_ci'
	);
} else {
	$config = array(
		'driver' => 'mysql', 
		'host' => 'localhost', 
		'database' => 'fifa', 
		'username' => 'root', 
		'password' => 'stream', 
		'charset' => 'utf8', 
		'collation' => 'utf8_unicode_ci'
	);
}

new \Pixie\Connection('mysql', $config, 'QB');

if(isset($_ENV["UPLOAD_PATH"])){
	$uploadPath = $_ENV["UPLOAD_PATH"];
} else {
	$uploadPath = "../uploads/";
}

$klein = new \Klein\Klein();

$klein->respond('POST', '/upload', function () {
	global $uploadPath;
	header('Content-Type: application/json');

	if(isset($_POST["id"]) && isset($_FILES["file"])){
		$query = QB::table('uploads')->where('id', '=', $_POST["id"]);

		$results = $query->get();

		if(isset($results[0])){
			if($results[0]->uploaded == 0){
				if ($_FILES["file"]["error"] == UPLOAD_ERR_OK) {
					$tmp_name = $_FILES["file"]["tmp_name"];

					$name = basename($_FILES["file"]["name"]);

					mkdir("$uploadPath/".$_POST["id"]);

					move_uploaded_file($tmp_name, "$uploadPath/".$_POST["id"]."/$name");

					QB::table('uploads')->where('id', $_POST["id"])->update(["uploaded" => 1, "filename" => $name, "filetype" => $_FILES["file"]["type"]]);

					return json_encode(["status" => "ok", "message" => "Upload ok. "]);
				} else {
					return json_encode(["error" => "error", "message" => "Upload error. "]);
				}
			} else {
				return json_encode(["error" => "error", "message" => "File uploaded. "]);
			}
		} else {
			return json_encode(["error" => "error", "message" => "Bad id. "]);
		}
	} else {
		return json_encode(["error" => "error", "message" => "Wront fields. "]);
	}
});

$klein->respond('GET', "/files/[:id]/[:name]", function($request, $response){
	global $uploadPath;

	$query = QB::table('uploads')->where('id', '=', $request->param('id'));

	$results = $query->get();
	if(isset($results[0])){
		if($results[0]->uploaded == 1){
			if($results[0]->filename == $request->param('name')){
				header('Content-Type: '.$results[0]->filetype);
				echo file_get_contents("$uploadPath/".$results[0]->id."/".$results[0]->filename);

				return;
			}
		}
	}

	$response->code(404);
});

$klein->dispatch();

