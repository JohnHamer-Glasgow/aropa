<xsl:stylesheet
    version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:php="http://php.net/xsl">
  <xsl:output method="xml" version="1.0" encoding="UTF-8" indent="yes"/>
  <xsl:template match="/results">
    <xsl:variable name="markCols" select="count(mark-items/mark)"/>
    <xsl:variable name="commentCols" select="count(comment-items/comment)"/>
    <xsl:variable name="totalCols" select="$markCols + $commentCols"/>
    <worksheet
	xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
	xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
      <dimension>
	<xsl:attribute name="ref">
          <xsl:value-of select="concat('A1:',php:function('cellRef0', $totalCols), count(responses/response) + 3)" />
	</xsl:attribute>
      </dimension>
      <sheetViews>
	<sheetView tabSelected="1" workbookViewId="0">
	  <selection activeCell="A1" sqref="A1"/>
	</sheetView>
      </sheetViews>
      <sheetFormatPr baseColWidth="10" defaultRowHeight="15"/>
      <cols>
	<col min="1" width="9" style="1">
	  <xsl:attribute name="max">
	    <xsl:value-of select="$markCols + 3" />
	  </xsl:attribute>
	</col>
	<col width="44" customWidth="1">
	  <xsl:attribute name="min">
	    <xsl:value-of select="$markCols + 4" />
	  </xsl:attribute>
	  <xsl:attribute name="max">
	    <xsl:value-of select="$totalCols + 4" />
	  </xsl:attribute>
	</col>
      </cols>
      <sheetData>
	<row r="1" ht="24">
	  <xsl:attribute name="spans">
	    <xsl:value-of select="concat('1:', $totalCols + 3)" />
	  </xsl:attribute>
	  <c r="A1" s="5" t="inlineStr"><is><t><xsl:value-of select="title"/></t></is></c>
	</row>
	<row r="2">
	  <xsl:attribute name="spans">
	    <xsl:value-of select="concat('1:', $totalCols + 3)" />
	  </xsl:attribute>
	  <c r="A2" s="2" t="inlineStr"><is><t><xsl:value-of select="left"/></t></is></c>
	  <c r="B2" s="2" t="inlineStr"><is><t><xsl:value-of select="right"/></t></is></c>
	  <c r="C2" s="2" t="inlineStr"><is><t>Time</t></is></c>
	  <xsl:for-each select="mark-items/mark">
	    <xsl:sort select="@item" data-type="number"/>
	    <c s="2" t="inlineStr">
	      <xsl:attribute name="r">
		<xsl:value-of select="concat(php:function('cellRef0', position() + 2), '2')"/>
	      </xsl:attribute>
	      <is>
		<t><xsl:value-of select="@label"/></t>
	      </is>
	    </c>
	  </xsl:for-each>
	  <xsl:for-each select="comment-items/comment">
	    <xsl:sort select="@item" data-type="number"/>
	    <c s="4" t="inlineStr">
	      <xsl:attribute name="r">
		<xsl:value-of select="concat(php:function('cellRef0', $markCols + position() + 2), '2')"/>
	      </xsl:attribute>
	      <is>
		<t><xsl:value-of select="@label"/></t>
	      </is>
	    </c>
	  </xsl:for-each>
	</row>
	<xsl:for-each select="responses/response">
	  <xsl:sort select="@author" />
	  <xsl:sort select="@reviewer" />
	  <xsl:variable name="row" select="position() + 2"/>
	  <row ht="60" customHeight="1">
	    <xsl:attribute name="r">
	      <xsl:value-of select="$row" />
	    </xsl:attribute>
	    <xsl:attribute name="spans">
	      <xsl:value-of select="concat('1:', $totalCols + 2)" />
	    </xsl:attribute>
	    <c s="3" t="inlineStr">
	      <xsl:attribute name="r">
		<xsl:value-of select="concat('A', $row)"/>
	      </xsl:attribute>
	      <is>
		<t><xsl:value-of select="@author"/></t>
	      </is>
	    </c>
	    <c s="3" t="inlineStr">
	      <xsl:attribute name="r">
		<xsl:value-of select="concat('B', $row)"/>
	      </xsl:attribute>
	      <is>
		<t><xsl:value-of select="@reviewer"/></t>
	      </is>
	    </c>
	    <c s="3" t="inlineStr">
	      <xsl:attribute name="r">
		<xsl:value-of select="concat('C', $row)"/>
	      </xsl:attribute>
	      <is>
		<t><xsl:value-of select="@time"/></t>
	      </is>
	    </c>
	    <xsl:for-each select="mark">
	      <xsl:sort select="@item" data-type="number"/>
	      <c s="3">
		<xsl:attribute name="r">
		  <xsl:value-of select="concat(php:function('cellRef0', position() + 2), $row)"/>
		</xsl:attribute>
		<xsl:choose>
		  <xsl:when test="@t = 'n'">
		    <v><xsl:value-of select="text()"/></v>
		  </xsl:when>
		  <xsl:otherwise>
		    <xsl:attribute name="t">
		      <xsl:value-of select="'inlineStr'"/>
		    </xsl:attribute>
		    <is>
		      <t><xsl:value-of select="text()"/></t>
		    </is>
		  </xsl:otherwise>
		</xsl:choose>
	      </c>
	    </xsl:for-each>
	    <xsl:for-each select="comment">
	      <xsl:sort select="@item" data-type="number"/>
	      <c s="1" t="inlineStr">
		<xsl:attribute name="r">
		  <xsl:value-of select="concat(php:function('cellRef0', position() + $markCols + 2), $row)"/>
		</xsl:attribute>
		<is>
		  <t><xsl:value-of select="text()"/></t>
		</is>
	      </c>
	    </xsl:for-each>
	  </row>
	</xsl:for-each>
      </sheetData>
      <pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>
    </worksheet>
  </xsl:template>
</xsl:stylesheet>
