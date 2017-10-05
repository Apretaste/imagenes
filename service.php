<?php

class Imagenes extends Service
{
	/**
	 * Function executed when the service is called
	 *
	 * @param Request
	 * @return Response
	 * */
	public function _main(Request $request)
	{
		// do not allow blank searches
		if(empty($request->query))
		{
			$response = new Response();
			$response->setCache();
			$response->setResponseSubject("Que imagenes desea buscar?");
			$response->createFromTemplate("home.tpl", array());
			return $response;
		}

		// search and return images
		return $this->getResults($request->query);
	}

	/**
	 * Function returns all results avaiable in the api.
	 *
	 * @return \ Response
	 */
	private function getResults($query)
	{
		$pos = strpos($query, "^");
		if ($pos != false){
			$start = substr($query, $pos+1);
			$query = substr($query, 0, $pos);
		} else $start = 1;

		// load from cache if exists
		$nomCacheFile = preg_replace('/[\.\/:?=&\'\s]/', '_', $query);
		$cacheFile = $this->utils->getTempDir() . date("dmY") . "_".$nomCacheFile."_start_".$start."_cacheFile.tmp";

		// load from the internet or cache
		if(file_exists($cacheFile)){
			$response = file_get_contents($cacheFile);
		}else{
			// get the Google parms
			$di = \Phalcon\DI\FactoryDefault::getDefault();
			$ApiKey = $di->get('config')['google']['apikey'];
			$SearchId = $di->get('config')['google']['searchid'];
			$encodedQuery = urlencode($query);

			$uri = "https://www.googleapis.com/customsearch/v1?key=$ApiKey&cx=$SearchId&q=$encodedQuery&searchType=image&alt=json&start=$start&imgSize=small&fileType=jpg"; //
			$response = $this->getUrl($uri);
			file_put_contents($cacheFile, $response); // save cache file
		}

		$result = json_decode($response);

		// error if the search returns empty
		if(empty($result))
		{
			$response = new Response();
			$response->setResponseSubject("Su busqueda no genero resultados");
			$response->createFromText("Su busqueda <b>{$query}</b> no gener&oacute; ning&uacute;n resultado. Por favor cambie los t&eacute;rminos de b&uacute;squeda e intente nuevamente.");
			return $response;
		}

		// get the first 6 imagenes
		$imageLinks = array();
		for ($i=0; $i < 6; $i++) {
			// get current item or break
			if(empty($result->items[$i])) break;
			else $item = $result->items[$i];

			// clean string after ?
			$pos = strpos($item->link, "?");
			if($pos) $item->link = substr($item->link, 0, $pos);

			$image = array(
				"thumbnailLink" => $item->image->thumbnailLink,
				"thumbnailWidth" => $item->image->thumbnailWidth,
				"thumbnailHeight" => $item->image->thumbnailHeight,
				"link" => $item->link,
				"title" => $this->utils->removeTildes($item->title),
				"width" => $item->image->width,
				"height" => $item->image->height
			);
			$imageLinks[] = $image;
		}

		// get the images to embed into the email
		$images = array();
		$imageStructure = array();
		foreach ($imageLinks as $image) {
			$imgCacheFile = $this->processImage($image['thumbnailLink']);
			$image['name'] = $imgCacheFile;
			$images[$imgCacheFile] = $imgCacheFile;
			$imageStructure[] = $image;
		}

		// create an object to send to the template
		$responseContent = array(
			"searchTerms" => isset($result->queries) ? $result->queries->request[0]->searchTerms : [],
			"imageLinks" => $imageStructure,
			"nextPageStart" => isset($result->queries->nextPage[0]->startIndex) ? $result->queries->nextPage[0]->startIndex : '0',
			"rowNumbers" => (int) count($imageLinks) / 2,
			"titulo" => $query
		);

		// create the response
		$response = new Response();
		$response->setResponseSubject("Imagenes relacionadas con $query");
		$response->createFromTemplate("results.tpl", $responseContent, $images);
		return $response;
	}

	/**
	 * Process an image
	 */
	private function processImage($Link)
	{
		$imgSource = $Link;
		$extension = substr($imgSource, -4);
		$imgSourceWithoutSimbols = preg_replace('/[\.\/:?=&\'\s%]/', '_', $imgSource);
		$imgCacheFile = $this->utils->getTempDir() . $imgSourceWithoutSimbols. "_CacheFile.png";

		if( ! file_exists($imgCacheFile))
		{
			$imgSource = $this->getUrl($imgSource);
			if ($imgSource != false)
			{
				if (strtolower($extension) != '.png')
				{
					if (strtolower($extension) == '.svg')
					{
						$image = new Imagick();
						$image->readImageBlob($imgSource); //imagen en svg
						$image->setImageFormat("png24");
						$image->resizeImage(1024, 768, imagick::FILTER_LANCZOS, 1);
						$image->writeImage($imgCacheFile); //imagen png
					}
					else
					{
						//probado para JPG, JPEG, and GIF
						imagepng(imagecreatefromstring($imgSource), $imgCacheFile);
					}
				}
				else
				{
					file_put_contents($imgCacheFile, $imgSource);
				}
			}
			else
			{
				$image = new Imagick();
				$dibujo = new ImagickDraw();
				$dibujo->setFontSize( 30 );
				$image->newImage(100, 100, new ImagickPixel('#d3d3d3')); //imagen fondo gris
				$image->annotateImage($dibujo, 10, 45, 0, ' 404!'); // Crear texto
				$image->setImageFormat("png24");
				$image->resizeImage(1024, 768, imagick::FILTER_LANCZOS, 1);
				$image->writeImage($imgCacheFile);
			}
		}

		return $imgCacheFile;
	}

	private function getUrl($url, &$info = [])
	{
	    $url = str_replace("//", "/", $url);
	    $url = str_replace("http:/","http://", $url);
        $url = str_replace("https:/","https://", $url);

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);

		$default_headers = [
			"Cache-Control" => "max-age=0",
			"Origin" => "{$url}",
			"User-Agent" => "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.125 Safari/537.36",
			"Content-Type" => "application/x-www-form-urlencoded"
		];

		$hhs = [];
		foreach ($default_headers as $key => $val)
			$hhs[] = "$key: $val";

		curl_setopt($ch, CURLOPT_HTTPHEADER, $hhs);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $html = curl_exec($ch);

		$info = curl_getinfo($ch);

		if ($info['http_code'] == 301)
			if (isset($info['redirect_url']) && $info['redirect_url'] != $url)
				return $this->getUrl($info['redirect_url'], $info);

		curl_close($ch);

		return $html;
	}
}
