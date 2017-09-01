<?php

use Symfony\Component\DomCrawler\Crawler;
// api key:  AIzaSyCoQLBgC1_Fzd9SMFS0dqiGYy2UoMp7A8U 
// https://www.googleapis.com/customsearch/v1?key=AIzaSyCoQLBgC1_Fzd9SMFS0dqiGYy2UoMp7A8U&cx=004652491246805662376:hjkfnl1btua&q=flower&searchType=image&fileType=jpg&imgSize=small&alt=json
class Imagenes extends Service{
	private $googleApisApiKey = "AIzaSyCoQLBgC1_Fzd9SMFS0dqiGYy2UoMp7A8U";
	private $googleCustomSearchId = "004652491246805662376:hjkfnl1btua";
	/**
	 * Function executed when the service is called
	 * 
	 * @param Request
	 * @return Response
	 * */
	public function _main(Request $request)
	{	
		if (empty($request->query)){
			$response = new Response();
			$response->setResponseSubject("¿Cuales imagenes deseas?");
			$response->createFromText("<center>Lo sentimos, pero no nos envi&oacute; ning&uacute;n texto a buscar.</br></br>Escriba un nuevo email y en el asunto ponga la palabra IMAGENES seguida las palabras relacionadas con las imagenes que desea ver.</br>Por ejemplo:</br>
			Asunto: IMAGENES dibujo casa pequeña</center>");
			return $response;
		}else{
			return $this->getResults($request->query);
		}
	}

	/**
	 * Function executed when the sub-service IMAGEN is called 
	 * 
	 * @param Request
	 * @return Response
	 * */
	public function _imagen(Request $request)
	{	
		if (empty($request->query)){
			$response = new Response();
			$response->setResponseSubject("¿Cual imagen deseas?");
			$response->createFromText("<center>Lo sentimos, pero no nos envi&oacute; ning&uacute;na imagen a descargar.</br></br>Escriba un nuevo email y en el asunto ponga la palabras IMAGENES IMAGEN seguida la URL de la imagen que deseas descargar.</br>Por ejemplo:</br>
			Asunto: IMAGENES IMAGEN http://www.dominio.com/imagenadescargar.jpg</center>");
			return $response;
		}else{
			return $this->getImage($request->query);
		}
	}

	/**
     * Function returns all results avaiable in the api.
     * 
     * @return \ Response
     */        
    private function getResults($query) {
    	$pos = strpos($query, "^");
    	if ($pos != false){
    		$start = substr($query, $pos+1);
    		$query = substr($query, 0, $pos-1);
    	}else{
    		$start = 1;
    	}
    	
    	$titulo = "Imagenes relacionadas con '".$query."'";
        $uri = 'https://www.googleapis.com/customsearch/v1?key='.$this->googleApisApiKey.'&cx='.$this->googleCustomSearchId.'&q='.urlencode($query).'&searchType=image&alt=json&start='.$start; //&imgSize=small&fileType=jpg
        // load from cache if exists
        $nomCacheFile = preg_replace('/[\.\/:?=&\'\s]/', '_', $query);
        $di = \Phalcon\DI\FactoryDefault::getDefault();
        $wwwroot = $di->get('path')['root'];

        $cacheFile = "$wwwroot/temp/" . date("dmY") . "_".$nomCacheFile."_start_".$start."_cacheFile.tmp";

        if(file_exists($cacheFile)){
            $response = file_get_contents($cacheFile);
        }else{
            $response = file_get_contents($uri); 
            // save cache file
            file_put_contents($cacheFile, $response);
        }
        
        $result = json_decode($response);
        // error if the searche return empty
		if(empty($result)){
			$response = new Response();
			$response->setResponseSubject("Su busqueda no genero resultados");
			$response->createFromText("Su busqueda <b>{$query}</b> no gener&oacute; ning&uacute;n resultado. Por favor cambie los t&eacute;rminos de b&uacute;squeda e intente nuevamente.");
			return $response;
		}

        $imageLinks = array();
        foreach ($result->items as $item) { 
        	$image = array(
        		"thumbnailLink" => $item->image->thumbnailLink, 
        		"thumbnailWidth" => $item->image->thumbnailWidth, 
        		"thumbnailHeight" => $item->image->thumbnailHeight,
        		"link" => $item->link, 
        		"title" => $item->title, 
        		"width" => $item->image->width, 
        		"height" => $item->image->height
        	);
        	$imageLinks[] = $image;
        }

        // create a json object to send to the template
		$responseContent = array(
			"searchTerms" => $result->queries->request[0]->searchTerms,
			"imageLinks" => $imageLinks,
			"nextPageStart" => $result->queries->nextPage[0]->startIndex,
			"rowNumbers" => (int) count($imageLinks) / 2 
		);

		// get the images to embed into the email
		$images = array();
		$imageNames = array();
       	foreach ($imageLinks as $image) {
       		$imgCacheFile = $this->processImage($image['thumbnailLink']);
			$imageNames[] = $imgCacheFile;
			$images[$imgCacheFile] = $imgCacheFile;
       	}
        $responseContent["imageNames"] = $imageNames;
        $responseContent["titulo"] = $titulo;
        //return $result;

		// create the response
		$response = new Response();
		$response->setResponseSubject($titulo);
		$response->createFromTemplate("showResults.tpl", $responseContent, $images);
		return $response;
    }

    private function processImage($Link){
    	$imgSource = $Link;
        $extension = substr($imgSource, -4);

        /*if(!preg_match("/\.{1}.{3}/", $extension)){
           	$pos = strpos($imgSource, $extension);
           	$imageNames[]=$pos;
        }*/
        $di = \Phalcon\DI\FactoryDefault::getDefault();
      	$wwwroot = $di->get('path')['root'];
       	$imgSourceWithoutSimbols = preg_replace('/[\.\/:?=&\'\s%]/', '_', $imgSource);
       	$imgCacheFile = "$wwwroot/temp/". $imgSourceWithoutSimbols. "_CacheFile.png"; 
        	
		if(!file_exists($imgCacheFile)){
			$imgSource = $this->file_get_contents_curl($imgSource);
			if ($imgSource != false){
				if (strtolower($extension) != '.png'){
					if (strtolower($extension) == '.svg'){
						$image = new Imagick();
						$image->readImageBlob($imgSource); //imagen en svg
						$image->setImageFormat("png24");
						$image->resizeImage(1024, 768, imagick::FILTER_LANCZOS, 1); 
						$image->writeImage($imgCacheFile); //imagen png
					}else{
						//probado para JPG, JPEG, and GIF
						imagepng(imagecreatefromstring($imgSource), $imgCacheFile);
					}
				}else{
					file_put_contents($imgCacheFile, $imgSource);
				}
			}else{
				$image = new Imagick();
				$dibujo = new ImagickDraw();
				$dibujo->setFontSize( 30 );
				
				$image->newImage(100, 100, new ImagickPixel('#d3d3d3')); //imagen fondo gris
				// Crear texto 
				$image->annotateImage($dibujo, 10, 45, 0, ' 404!');
				$image->setImageFormat("png24");
				$image->resizeImage(1024, 768, imagick::FILTER_LANCZOS, 1); 
				$image->writeImage($imgCacheFile);
			}
		}
		return $imgCacheFile;
    }

    /**
     * Function returns the image.
     * 
     * @return \ Response
     */        
    private function getImage($query) {
    	$pos = strpos($query, "^");
    	if ($pos != false){
    		$searchTerms = substr($query, $pos+1);
    		$query = substr($query, 0, $pos-1);
    		$pos = strpos($searchTerms, "*");
	    	if ($pos != false){
	    		$start = substr($searchTerms, $pos+1);
	    		$searchTerms = substr($searchTerms, 0, $pos-1);
	    	}else{
	    		$start = 1;
	    	}	
    	}else{
    		$searchTerms = "imagenes";
    	}

    	$imageUrl = $query;
    	$imgCacheFile = $this->processImage($imageUrl);
    	// create a json object to send to the template
		$responseContent = array(
			"titulo" => "La imagen que t&uacute; quer&iacute;as:",
			"searchTerms" => $searchTerms,
			"start" => $start,
			"imagen" => $imgCacheFile
		);

		// get the images to embed into the email
		$images = array(
			"imagen" => $imgCacheFile
		);
        //return $result;

		// create the response
		$response = new Response();
		$response->setResponseSubject("La imagen que t&uacute; quer&iacute;as:");
		$response->createFromTemplate("showImage.tpl", $responseContent, $images);
		return $response;
    }

    private function file_get_contents_curl($url){
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Set curl to return the data instead of printing it to the browser.
	  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
      curl_setopt($ch, CURLOPT_URL, $url);
      $data = curl_exec($ch);
      curl_close($ch);
      return $data;
    }

}
