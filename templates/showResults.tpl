<center>
<h1>{$titulo}</h1>
{space10}
<table style="text-align:center" width="100%">
	{for $i=1 to {$rowNumbers}}
		<tr>
		    <th {if not isset($imageLinks[{$i+$rowNumbers}])} colspan="2" {/if}>
		    	{link href="IMAGENES IMAGEN {$imageLinks[{$i}].link} ^{$searchTerms} *{$nextPageStart}" caption={img src="{$imageNames[{$i}]}" alt="{$imageLinks[{$i}].link}" width="{$imageLinks[{$i}].thumbnailWidth}" height="{$imageLinks[{$i}].thumbnailHeight}"} }
		    </th>
		    {if isset($imageLinks[{$i+$rowNumbers}])}
		    <th>
		    	{link href="IMAGENES IMAGEN {$imageLinks[{$i+$rowNumbers}].link} ^{$searchTerms} *{$nextPageStart}" caption={img src="{$imageNames[{$i+$rowNumbers}]}" alt="{$imageLinks[{$i+$rowNumbers}].link}" width="{$imageLinks[{$i+$rowNumbers}].thumbnailWidth}" height="{$imageLinks[{$i+$rowNumbers}].thumbnailHeight}"} }
		    </th>
		    {/if}
		</tr>
		<tr>
			<td {if not isset($imageLinks[{$i+$rowNumbers}])} colspan="2" {/if}>
				{link href="IMAGENES IMAGEN {$imageLinks[{$i}].link} ^{$searchTerms} *{$nextPageStart}" caption="Descargar"}
			</td>
			{if isset($imageLinks[{$i+$rowNumbers}])} 
			<td>
				{link href="IMAGENES IMAGEN {$imageLinks[{$i+$rowNumbers}].link} ^{$searchTerms} *{$nextPageStart}" caption="Descargar"} 
			</td>
			{/if}
		</tr>
	{/for}
</table>
{space15}
<table style="text-align:center;" width="100%">
	<tr>
	    <td style="" colspan="1">
		    {button href="IMAGENES {$searchTerms} ^{$nextPageStart}" caption="Ver m&aacute;s" color="green" size="small"}
		</td>
	</tr>
</table>
{space10}