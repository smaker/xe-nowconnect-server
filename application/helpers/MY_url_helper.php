<?php
function getHostByUrl($url)
{
	$url_info = parse_url($url);
	if(!$url_info)
	{
		return FALSE;
	}

	$host = $url_info['host'] . (isset($url_info['port']) ? ':' . $url_info['port'] : '');

	(substr($host, -1, 1) == '/') AND $host = substr($host, 0, strlen($host) -1);

	return $host;
}

function getHostByUrlWithPath($url)
{
	$url_info = parse_url($url);
	if(!$url_info)
	{
		return FALSE;
	}

	$host = $url_info['host'] . (isset($url_info['port']) ? ':' . $url_info['port'] : '') . $url_info['path'];

	(substr($host, -1, 1) == '/') AND $host = substr($host, 0, strlen($host) -1);

	return $host;
}