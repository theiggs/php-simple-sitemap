<?php

namespace TheIggs\SimpleSitemap;

/**
 * Simple (to use) streaming sitemap generator class.
 *
 * Generate your sitemaps with ease! Thanks to @icamys for inspiration.
 * For sitemap limitations see https://www.sitemaps.org/protocol.html
 *
 * @package TheIggs\SimpleSitemap
 * @see https://github.com/icamys/php-sitemap-generator/
 * @see https://www.sitemaps.org/protocol.html
 */
class StreamingSimpleSitemap
{
    /**
     * "XML" parameter index (for use with sitemap and index chunks).
     */
    const CHUNK_PARAM_XML = 0;

    /**
     * "Filename" parameter index (for use with sitemap and index chunks).
     */
    const CHUNK_PARAM_FILENAME = 1;

    /**
     * XML byte length added by the tags for the <changefreq> element.
     *
     * The <changefreq> element is optional. <changefreq></changefreq> adds
     * 25 bytes.
     */
    const LENGTH_CHANGEFREQ = 25;

    /**
     * XML byte length added by the tags for the <lastmod> element.
     *
     * The <lastmod> element is optional. <lastmod></lastmod> adds 19 bytes.
     */
    const LENGTH_LASTMOD = 19;

    /**
     * XML byte length added by the tags for the <loc> element.
     *
     * The <loc> element exists for every non-blank URL added. <loc></loc> adds
     * 11 bytes.
     */
    const LENGTH_LOC = 11;

    /**
     * XML byte length added by the tags for the <priority> element.
     *
     * The <priority> element is optional. <priority></priority> adds 21 bytes.
     */
    const LENGTH_PRIORITY = 21;

    /**
     * XML byte length added by the tags for the <sitemap> element.
     *
     * The <sitemap> element exists for every sitemap added. <sitemap></sitemap>
     * adds 19 bytes.
     */
    const LENGTH_SITEMAP = 19;

    /**
     * XML byte length added by the tags for the <url> element.
     *
     * The <url> element exists for every non-blank URL added. <url></url> adds
     * 11 bytes.
     */
    const LENGTH_URL = 11;

    /**
     * XML byte length added by SimpleXML.
     *
     * SimpleXML adds 2 line breaks when exporting XML: 1 after the <?xml...?>
     * declaration and 1 at the end of the file.
     */
    const LENGTH_XML = 2;

    /**
     * Maximum allowed sitemap index file size, in bytes.
     */
    const MAX_INDEX_SIZE = 10485760;

    /**
     * Maximum allowed sitemap file size, in bytes.
     */
    const MAX_SITEMAP_SIZE = 10485760;

    /**
     * Maximum allowed number of sitemaps per index file.
     */
    const MAX_SITEMAPS_PER_INDEX = 50000;

    /**
     * Maximum allowed number of URLs per sitemap file.
     */
    const MAX_URLS_PER_SITEMAP = 50000;

    /**
     * If true, sitemap index files will be created if there are two or more
     * sitemap chunks.
     *
     * @var bool
     */
    public $createIndexes = true;

    /**
     * If true, compressed .xml.gz sitemap index files will be created
     * instead of plain .xml files.
     *
     * @var bool
     */
    public $gzipIndexes = false;

    /**
     * If true, compressed .xml.gz sitemap files will be created instead of
     * plain .xml files.
     *
     * @var bool
     */
    public $gzipSitemaps = true;

    /**
     * Default name for a sitemap index file.
     *
     * @var string
     */
    public $indexFilename = 'sitemap-index.xml';

    /**
     * If true, the addUrl() and addUrls() functions will accept relative URLs
     * instead of absolute URLs. The base URL will be prepended automatically.
     *
     * @var bool
     */
    public $relativeUrls = true;

    /**
     * Default name for a sitemap file.
     *
     * @var string
     */
    public $sitemapFilename = 'sitemap.xml';

    /**
     * The site's base URL.
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Base path.
     *
     * Use this if your sitemap files should be stored in other directory
     * than this script.
     *
     * @var string
     */
    protected $basePath = '';

    /**
     * Names of the written files in a format suitable for further use.
     *
     * @var array
     */
    protected $filenames = [];

    /**
     * Array of sitemap index chunks.
     *
     * Each array element contains an SplFixedArray of two elements.
     * The 0th element holds the SimpleXMLElement object where the XML code
     * of that sitemap index chunk is stored, and the 1st element holds
     * the filename.
     *
     * @var \SplFixedArray[]
     */
    protected $indexes = [];

    /**
     * Number of <sitemap> elements in the current sitemap index chunk.
     *
     * @var int
     */
    protected $indexChunkCount = 0;

    /**
     * Length (in bytes) of the XML code in the current sitemap index chunk.
     *
     * @var int
     */
    protected $indexChunkLength = 0;

    /**
     * XML header code for sitemap index files.
     *
     * @var string
     */
    protected $indexHeader = '';

    /**
     * Array of sitemap chunks.
     *
     * Each array element contains an SplFixedArray of two elements.
     * The 0th element holds the SimpleXMLElement object where the XML code
     * of that sitemap chunk is stored, and the 1st element holds the filename.
     *
     * @var \SplFixedArray[]
     */
    protected $sitemaps = [];

    /**
     * Number of <url> elements in the current sitemap chunk.
     *
     * @var int
     */
    protected $sitemapChunkCount = 0;

    /**
     * Length (in bytes) of the XML code in the current sitemap chunk.
     *
     * @var int
     */
    protected $sitemapChunkLength = 0;

    /**
     * XML header code for sitemap files.
     *
     * @var string
     */
    protected $sitemapHeader = '';

    /**
     * Constructor.
     *
     * @param string $baseUrl The site's base URL. The trailing slash
     *     will be added automatically if needed.
     * @param string $basePath The path where sitemaps will be stored.
     */
    public function __construct($baseUrl, $basePath = '')
    {
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
        if ($basePath !== '') {
            $basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }
        $this->basePath = $basePath;
        $this->indexHeader = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<sitemapindex xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            . ' xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9'
            . ' http://www.sitemaps.org/schemas/sitemap/0.9/siteindex.xsd"'
            . ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            . '</sitemapindex>';
        $this->sitemapHeader = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            . ' xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9'
            . ' http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"'
            . ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    }

    /**
     * Adds multiple URLs to the sitemap.
     *
     * Each nested array can contain 1 to 4 values indexed from 0 to 3 or keyed
     * 'url', 'lastmod', 'changefreq', and 'priority'.
     *
     * @param array[] $urlArray An array of URLs.
     * @return bool
     */
    public function addUrls($urlArray)
    {
        if (!is_array($urlArray)) {
            trigger_error(
                'An array should be given as the argument.',
                E_USER_WARNING
            );
            return false;
        }
        static $paramArray = ['url', 'lastmod', 'changefreq', 'priority'];
        foreach ($urlArray as $urlItem) {
            $newElement = [];
            foreach ($paramArray as $key => $value) {
                if (isset($urlItem[$value])) {
                    $newElement[$value] = $urlItem[$value];
                } elseif (isset($urlItem[$key])) {
                    $newElement[$value] = $urlItem[$key];
                } else {
                    $newElement[$value] = '';
                }
            }
            $this->addUrl(
                $newElement['url'],
                $newElement['lastmod'],
                $newElement['changefreq'],
                $newElement['priority']
            );
        }
        return true;
    }

    /**
     * Adds a single URL element to the sitemap.
     *
     * @param string $url The URL to add. If relative URLs are used, the leading
     *     slash (if any) will be removed automatically.
     * @param string $lastModified Date of the last modification of the URL's
     *     content in ISO 8601 datetime format.
     * @param string $changeFrequency How often search engines should revisit
     *     this URL.
     * @param string $priority Priority of the URL on your site.
     * @return bool
     * @see http://en.wikipedia.org/wiki/ISO_8601
     * @see http://php.net/manual/en/function.date.php
     */
    public function addUrl($url, $lastModified = '', $changeFrequency = '',
        $priority = ''
    ) {
        $url = (string) $url;
        if ($this->relativeUrls) {
            $url = $this->baseUrl . ltrim($url, '/');
        }
        if ($url === '') {
            trigger_error(
                'The URL argument is mandatory. Please provide a URL.',
                E_USER_WARNING
            );
            return false;
        }
        if (strlen($url) > 2048) {
            trigger_error(
                'URL length must not be bigger than 2048 characters.',
                E_USER_WARNING
            );
            return false;
        }
        if (count($this->sitemaps) < 1) {
            $this->addSitemapChunk();
        }
        if ($this->sitemapChunkCount >= static::MAX_URLS_PER_SITEMAP) {
            $this->addSitemapChunk();
        }
        $element = [
            'loc'        => $this->escapeUrl($url),
            'lastmod'    => (string) $lastModified,
            'changefreq' => (string) $changeFrequency,
            'priority'   => (string) $priority,
        ];
        $elementLength = $this->getUrlElementLength($element);
        if ($this->sitemapChunkLength + $elementLength > static::MAX_SITEMAP_SIZE) {
            $this->addSitemapChunk();
        }
        $sitemapChunk = $this->getSitemapChunk();
        /** @var \SimpleXMLElement $xml */
        $xml = $sitemapChunk[static::CHUNK_PARAM_XML];
        $row = $xml->addChild('url');
        $row->addChild('loc', $element['loc']);
        if ($element['lastmod'] !== '') {
            $row->addChild('lastmod', $element['lastmod']);
        }
        if ($element['changefreq'] !== '') {
            $row->addChild('changefreq', $element['changefreq']);
        }
        if ($element['priority'] !== '') {
            $row->addChild('priority', $element['priority']);
        }
        $this->sitemapChunkCount++;
        $this->sitemapChunkLength += $elementLength;
        return true;
    }

    /**
     * Returns the URLs for the written sitemap in a format suitable to feed
     * to search engines.
     *
     * @return string[]
     */
    public function getUrls()
    {
        if (count($this->filenames) < 1) {
            trigger_error('To get the sitemap URLs, first write the sitemap'
                . ' into files with the writeSitemap() function.');
            return [];
        }
        $urls = [];
        foreach ($this->filenames as $filename) {
            $urls[] = $this->baseUrl . $filename;
        }
        return $urls;
    }

    /**
     * Writes created sitemap into files.
     *
     * @return bool
     */
    public function writeSitemap()
    {
        if (count($this->sitemaps) < 1) {
            trigger_error(
                'To write the sitemap into files, first create the sitemap.',
                E_USER_WARNING
            );
            return false;
        }
        $this->setSitemapFilenames();
        foreach ($this->sitemaps as $sitemap) {
            $this->writeFile(
                $sitemap[static::CHUNK_PARAM_FILENAME],
                $sitemap[static::CHUNK_PARAM_XML],
                $this->gzipSitemaps
            );
        }
        if ($this->createIndexes && $this->createIndex()) {
            foreach ($this->indexes as $index) {
                $this->writeFile(
                    $index[static::CHUNK_PARAM_FILENAME],
                    $index[static::CHUNK_PARAM_XML],
                    $this->gzipIndexes
                );
            }
        }
        $this->setFilenames();
        return true;
    }

    /**
     * Adds another chunk to the array of sitemap indexes.
     *
     * @return void
     */
    protected function addIndexChunk()
    {
        $chunkDummy = new \SplFixedArray(2);
        $chunkDummy[static::CHUNK_PARAM_XML]
            = new \SimpleXMLElement($this->indexHeader);
        $chunkDummy[static::CHUNK_PARAM_FILENAME] = '';
        $this->indexes[] = $chunkDummy;
        end($this->indexes);
        $this->indexChunkCount = 0;
        $this->indexChunkLength
            = strlen($this->indexHeader)
            + static::LENGTH_XML;
    }

    /**
     * Adds another chunk to the array of sitemaps.
     *
     * @return void
     */
    protected function addSitemapChunk()
    {
        $chunkDummy = new \SplFixedArray(2);
        $chunkDummy[static::CHUNK_PARAM_XML]
            = new \SimpleXMLElement($this->sitemapHeader);
        $chunkDummy[static::CHUNK_PARAM_FILENAME] = '';
        $this->sitemaps[] = $chunkDummy;
        end($this->sitemaps);
        $this->sitemapChunkCount = 0;
        $this->sitemapChunkLength
            = strlen($this->sitemapHeader)
            + static::LENGTH_XML;
    }

    /**
     * Creates sitemap index.
     *
     * The index is rebuilt on each call to this function.
     *
     * @return bool
     */
    protected function createIndex()
    {
        $this->indexes = [];
        if (count($this->sitemaps) < 2) {
            return false;
        }
        $this->addIndexChunk();
        foreach ($this->sitemaps as $sitemap) {
            if ($this->indexChunkCount >= static::MAX_SITEMAPS_PER_INDEX) {
                $this->addIndexChunk();
            }
            $element = [];
            $element['loc'] = $this->escapeUrl(
                $this->baseUrl . $sitemap[static::CHUNK_PARAM_FILENAME]
            );
            $element['lastmod'] = date('c');
            $elementLength = $this->getSitemapElementLength($element);
            if ($this->indexChunkLength + $elementLength > static::MAX_INDEX_SIZE) {
                $this->addIndexChunk();
            }
            $indexChunk = $this->getIndexChunk();
            /** @var \SimpleXMLElement $xml */
            $xml = $indexChunk[static::CHUNK_PARAM_XML];
            $row = $xml->addChild('sitemap');
            $row->addChild('loc', $element['loc']);
            $row->addChild('lastmod', $element['lastmod']);
            $this->indexChunkCount++;
            $this->indexChunkLength += $elementLength;
        }
        $this->setIndexFilenames();
        return true;
    }

    /**
     * Escapes the URL for export.
     *
     * @param string $url The original URL.
     * @return string
     */
    protected function escapeUrl($url)
    {
        return htmlspecialchars($url, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * Escapes quotes (' and ") inside the <urlset> and <sitemapindex> blocks.
     *
     * Though the sitemap protocol requires that single and double quotes
     * be escaped, the libxml library in its current implementation forces
     * these quotes to be left unescaped when they are found in element values.
     * So, this function is a kludge that is to be removed someday.
     *
     * @param string $xmlCode XML code
     * @return string
     * @see https://www.sitemaps.org/protocol.html
     * @see https://bugs.php.net/bug.php?id=49437
     * @todo Fix libxml. Remove the kludge. Rule the world.
     */
    protected function escapeXml($xmlCode)
    {
        $pos = strpos($xmlCode, '<sitemap>');
        if ($pos !== false) {
            $xmlCode
                = substr($xmlCode, 0, $pos)
                . str_replace(
                    ['"', "'"],
                    ['&quot;', '&apos;'],
                    substr($xmlCode, $pos)
                );
        }
        $pos = strpos($xmlCode, '<url>');
        if ($pos !== false) {
            $xmlCode
                = substr($xmlCode, 0, $pos)
                . str_replace(
                    ['"', "'"],
                    ['&quot;', '&apos;'],
                    substr($xmlCode, $pos)
                );
        }
        return $xmlCode;
    }

    /**
     * Gets the actual chunk from the array of sitemap indexes.
     *
     * @return \SplFixedArray
     */
    protected function getIndexChunk()
    {
        return current($this->indexes);
    }

    /**
     * Gets the actual chunk from the array of sitemaps.
     *
     * @return \SplFixedArray
     */
    protected function getSitemapChunk()
    {
        return current($this->sitemaps);
    }

    /**
     * Gets the length of a sitemap element.
     *
     * @param array $sitemapElement The sitemap element.
     * @return int
     */
    protected function getSitemapElementLength(array $sitemapElement)
    {
        $length
            = static::LENGTH_SITEMAP
            + static::LENGTH_LOC
            + strlen($sitemapElement['loc']);
        if ($sitemapElement['lastmod'] !== '') {
            $length
                += static::LENGTH_LASTMOD
                + strlen($sitemapElement['lastmod']);
        }
        return $length;
    }

    /**
     * Gets the length of a URL element.
     *
     * @param array $urlElement The URL element.
     * @return int
     */
    protected function getUrlElementLength(array $urlElement)
    {
        $length
            = static::LENGTH_URL
            + static::LENGTH_LOC
            + strlen($urlElement['loc']);
        if ($urlElement['lastmod'] !== '') {
            $length
                += static::LENGTH_LASTMOD
                + strlen($urlElement['lastmod']);
        }
        if ($urlElement['changefreq'] !== '') {
            $length
                += static::LENGTH_CHANGEFREQ
                + strlen($urlElement['changefreq']);
        }
        if ($urlElement['priority'] !== '') {
            $length
                += static::LENGTH_PRIORITY
                + strlen($urlElement['priority']);
        }
        return $length;
    }

    /**
     * Sets the filenames needed for exporting.
     *
     * @return void
     */
    protected function setFilenames()
    {
        $this->filenames = [];
        if (count($this->sitemaps) == 1) {
            $this->filenames[] = $this->sitemaps[0][static::CHUNK_PARAM_FILENAME];
            return;
        }
        if (count($this->indexes) > 0) {
            foreach ($this->indexes as $index) {
                $this->filenames[] = $index[static::CHUNK_PARAM_FILENAME];
            }
            return;
        }
        foreach ($this->sitemaps as $sitemap) {
            $this->filenames[] = $sitemap[static::CHUNK_PARAM_FILENAME];
        }
    }

    /**
     * Sets the filenames for sitemap index files.
     *
     * @return void
     */
    protected function setIndexFilenames()
    {
        $indexCount = count($this->indexes);
        $filename = $this->indexFilename . ($this->gzipIndexes ? '.gz' : '');
        if ($indexCount == 1) {
            $this->indexes[0][static::CHUNK_PARAM_FILENAME] = $filename;
        } elseif ($indexCount > 1) {
            $indexCounter = 1;
            foreach ($this->indexes as $index) {
                $index[static::CHUNK_PARAM_FILENAME] = str_replace(
                    '.xml',
                    $indexCounter++ . '.xml',
                    $filename
                );
            }
        }
    }

    /**
     * Sets the filenames for sitemap files.
     *
     * @return void
     */
    protected function setSitemapFilenames()
    {
        $sitemapCount = count($this->sitemaps);
        $filename = $this->sitemapFilename . ($this->gzipSitemaps ? '.gz' : '');
        if ($sitemapCount == 1) {
            $this->sitemaps[0][static::CHUNK_PARAM_FILENAME] = $filename;
        } elseif ($sitemapCount > 1) {
            $sitemapCounter = 1;
            foreach ($this->sitemaps as $sitemap) {
                $sitemap[static::CHUNK_PARAM_FILENAME] = str_replace(
                    '.xml',
                    $sitemapCounter++ . '.xml',
                    $filename
                );
            }
        }
    }

    /**
     * Writes data into a file.
     *
     * @param string $filename The filename.
     * @param \SimpleXMLElement $xml The XML to write.
     * @param bool $gzip If true, writes a gzipped file.
     * @return bool
     */
    protected function writeFile($filename, $xml, $gzip)
    {
        $xmlCode = $this->escapeXml($xml->asXML());
        $pathname = $this->basePath . $filename;
        if ($gzip) {
            return $this->writeGzip($pathname, $xmlCode);
        } else {
            return $this->writeXml($pathname, $xmlCode);
        }
    }

    /**
     * Writes data into a gzipped XML file.
     *
     * @param string $pathname The pathname.
     * @param string $xmlCode The XML code to write.
     * @return bool
     */
    protected function writeGzip($pathname, $xmlCode)
    {
        $file = gzopen($pathname, 'w');
        if (!$file) {
            return false;
        }
        gzwrite($file, $xmlCode);
        return gzclose($file);
    }

    /**
     * Writes data into an XML file.
     *
     * @param string $pathname The pathname.
     * @param string $xmlCode The XML code to write.
     * @return bool
     */
    protected function writeXml($pathname, $xmlCode)
    {
        return file_put_contents($pathname, $xmlCode);
    }
}
