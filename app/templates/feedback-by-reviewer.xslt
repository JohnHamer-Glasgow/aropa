<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
  <xsl:output method="xml" version="1.0" encoding="UTF-8" indent="yes"/>
  <xsl:key name="reviewer-key" match="/feedback/reviews/review" use="@reviewer" />
  <xsl:template match="/feedback">
    <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
		xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
      <w:body>
	<w:p>
	  <w:pPr>
	    <w:pStyle w:val="Title"/>
	  </w:pPr>
	  <w:r>
	    <w:t>Feedback for <xsl:value-of select="assignment"/></w:t>
	    <w:br/>
	    <w:t><xsl:value-of select="class"/></w:t>
	  </w:r>
	</w:p>
	<xsl:for-each select="reviews/review[generate-id(.)=generate-id(key('reviewer-key', @reviewer)[1])]">
	  <xsl:sort select="@reviewer" />
	  <w:p>
	    <w:pPr>
	      <w:pStyle w:val="Heading1"/>
	    </w:pPr>
	    <w:r>
	      <w:t>Reviewer: <xsl:value-of select="@reviewer"/></w:t>
	    </w:r>
	  </w:p>
	  <xsl:for-each select="key('reviewer-key', @reviewer)">
	    <xsl:sort select="@author" />
	    <w:p>
	      <w:pPr>
		<w:pStyle w:val="Heading2"/>
	      </w:pPr>
	      <w:r>
		<w:t>Review of <xsl:value-of select="@author"/>
		</w:t>
	      </w:r>
	    </w:p>
	    <w:p>
	      <xsl:for-each select="marks/mark">
		<xsl:sort select="@item" />
		<xsl:variable name="item" select="@item"/>
		<xsl:variable name="mark" select="@mark"/>
		<w:r>
		  <w:rPr>
		    <w:rStyle w:val="SubtitleChar"/>
		  </w:rPr>
		  <w:t>
		    <xsl:choose>
		      <xsl:when test="/feedback/mark-labels/mark[@item=$item]/label">
			<xsl:value-of select="/feedback/mark-labels/mark[@item=$item]/label"/>
		      </xsl:when>
		      <xsl:otherwise>
			<xsl:value-of select="@item"/>
		      </xsl:otherwise>
		    </xsl:choose>
		  </w:t>
		</w:r>
		<w:r>
		  <w:t>: <xsl:choose>
		    <xsl:when test="/feedback/mark-labels/mark[@item=$item]/grades/grade[@value=$mark]">
		      <xsl:value-of select="/feedback/mark-labels/mark[@item=$item]/grades/grade[@value=$mark]/@score"/>
		    </xsl:when>
		    <xsl:otherwise>
		      <xsl:value-of select="@mark"/>
		    </xsl:otherwise>
		  </xsl:choose>
		  </w:t>
		  <w:br/>
		</w:r>
	      </xsl:for-each>
	    </w:p>
	    <xsl:for-each select="comments/comment">
	      <xsl:sort select="@item" />
	      <xsl:variable name="item" select="@item"/>
	      <xsl:if test="/feedback/comment-labels/comment[@item=$item]">
		<w:p>
		  <w:pPr>
		    <w:pStyle w:val="Subtitle"/>
		  </w:pPr>
		  <w:r>
		    <w:t>
		      <xsl:value-of select="/feedback/comment-labels/comment[@item=$item]" />
		    </w:t>
		  </w:r>
		</w:p>
	      </xsl:if>
	      <w:altChunk r:id="{@chunk}" />
	    </xsl:for-each>
	  </xsl:for-each>
	</xsl:for-each>
      </w:body>
    </w:document>
  </xsl:template>
</xsl:stylesheet>
