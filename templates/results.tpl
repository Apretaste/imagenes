<h1>{$titulo}</h1>

{space5}

<table width="100%" cellpadding="10" cellspacing="0">
	{foreach $imageLinks as $image}
	<tr bgcolor="{cycle values="#f2f2f2,white"}">
		<td>{img src="{$image.name}" alt="{$image.link}" width="{$image.thumbnailWidth}" height="{$image.thumbnailHeight}"}</td>
		<td>{$image.title|truncate:100:"... "}</td>
		<td>-{$image.link}-{button href="WEB {$image.link}" caption="Ver grande" size="small" color="grey"}</td>
	</tr>
	{/foreach}
</table>

{space10}

<center>
	{button href="IMAGENES {$searchTerms} ^{$nextPageStart}" caption="Ver m&aacute;s"}
</center>
