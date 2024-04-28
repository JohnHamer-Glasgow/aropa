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
          <xsl:value-of select="concat('A1:',php:function('cellRef0', $totalCols), count(responses/response) + 2)" />
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
	    <xsl:value-of select="$markCols + 1" />
	  </xsl:attribute>
	</col>
	<col width="44" customWidth="1">
	  <xsl:attribute name="min">
	    <xsl:value-of select="$markCols + 2" />
	  </xsl:attribute>
	  <xsl:attribute name="max">
	    <xsl:value-of select="$totalCols + 1" />
	  </xsl:attribute>
	</col>
      </cols>
      <sheetData>
	<row r="1" ht="24">
	  <xsl:attribute name="spans">
	    <xsl:value-of select="concat('1:', $totalCols + 1)" />
	  </xsl:attribute>
	  <c r="A1" s="5" t="inlineStr"><is><t><xsl:value-of select="title"/></t></is></c>
	</row>
	<row r="2">
	  <xsl:attribute name="spans">
	    <xsl:value-of select="concat('1:', $totalCols + 1)" />
	  </xsl:attribute>
	  <c r="A2" s="2" t="inlineStr"><is><t>ID</t></is></c>
	  <xsl:for-each select="mark-items/mark">
	    <xsl:sort select="@item" />
	    <c s="2" t="inlineStr">
	      <xsl:attribute name="r">
		<xsl:value-of select="concat(php:function('cellRef0', position()), '2')"/>
	      </xsl:attribute>
	      <is>
		<t><xsl:value-of select="@label"/></t>
	      </is>
	    </c>
	  </xsl:for-each>
	  <xsl:for-each select="comment-items/comment">
	    <xsl:sort select="@item" />
	    <c s="4" t="inlineStr">
	      <xsl:attribute name="r">
		<xsl:value-of select="concat(php:function('cellRef0', $markCols + position()), '2')"/>
	      </xsl:attribute>
	      <is>
		<t><xsl:value-of select="@label"/></t>
	      </is>
	    </c>
	  </xsl:for-each>
	</row>
	<xsl:for-each select="responses/response">
	  <xsl:sort select="@pid" />
	  <xsl:variable name="row" select="position() + 2"/>
	  <row ht="60" customHeight="1">
	    <xsl:attribute name="r">
	      <xsl:value-of select="$row" />
	    </xsl:attribute>
	    <xsl:attribute name="spans">
	      <xsl:value-of select="concat('1:', $totalCols + 1)" />
	    </xsl:attribute>
	    <c s="3" t="inlineStr">
	      <xsl:attribute name="r">
		<xsl:value-of select="concat('A', $row)"/>
	      </xsl:attribute>
	      <is>
		<t><xsl:value-of select="@pid"/></t>
	      </is>
	    </c>
	    <xsl:for-each select="mark">
	      <c s="3" t="inlineStr">
		<xsl:attribute name="r">
		  <xsl:value-of select="concat(php:function('cellRef0', position()), $row)"/>
		</xsl:attribute>
		<is>
		  <t><xsl:value-of select="text()"/></t>
		</is>
	      </c>
	    </xsl:for-each>
	    <xsl:for-each select="comment">
	      <c s="1" t="inlineStr">
		<xsl:attribute name="r">
		  <xsl:value-of select="concat(php:function('cellRef0', position() + $markCols), $row)"/>
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
