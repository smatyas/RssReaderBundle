<?php

namespace MSo\RssReaderBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;


class DefaultController extends Controller
{
	# rss feed urls
	private $feed_urls = array(
        'http://origo.hu/contentpartner/rss/hircentrum/origo.xml',
		'http://hup.hu/node/feed',
	);
	
	private $feed_data; // collected xml feed data
    private $count = 10; // count of displayed/filtered items
    private $maxtime = 0; // max. pubDate
    private $error_msg = "";

    /**
     * Sort feeds by pubDate
     */
    static function sortFeeds($a, $b)
    {
        if ($a["item_date"] == $b["item_date"])
        {
            return 0;
        }

        return $a["item_date"] > $b["item_date"] ? -1 : 1;
    }

    /**
     * Filter callback function to filter items by max. time
     */
    private function maxTime($var)
    {
        return ($this->maxtime > 0 && $var["item_date"] < $this->maxtime);
    }
	
	/**
	 * Merges feeds into one array
	 */
	private function mergeFeeds()
	{
		usort($this->feed_data, array($this, "sortFeeds"));
	}

    /**
     * Filter items to display by count and date
     */
    private function filterItems()
    {
        // filter by time
        $request = $this->getRequest();
        $this->maxtime = $request->request->get('maxtime');
        if ($this->maxtime > 0)
        {
            $this->feed_data = array_filter($this->feed_data, array($this, "maxTime"));
        }

        // filter by count
        array_splice($this->feed_data, $this->count);

        // determine min. time
        $data_count = count($this->feed_data);
        if ($data_count > 0)
        {
            $this->maxtime = $this->feed_data[$data_count-1]["item_date"];
        }
        else
        {
            $this->maxtime = 0;
        }
    }


    /**
     * Reads the feeds and writes the merged list
     *
     * @param string $name
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction($name = "MSo")
    {
    	$this->feed_data = array();

        try
        {
            foreach ($this->feed_urls as &$feed)
            {
                // read the rss feed
                $x = simplexml_load_file($feed);
                if ($x === false)
                {
                    throw new Exception("XML load error.");
                }

                foreach ($x->channel->item as $item)
                {
                    $this->feed_data[] = array(
                        "title" => $x->channel->title,
                        "image" => $x->channel->image,
                        "item" => $item,
                        "item_date" => strtotime($item->pubDate),
                        "item_date_formatted" => date('Y. m. d. H:i', strtotime($item->pubDate)),
                    );
                }
            }
        }
        catch (Exception $e)
        {
            $this->error_msg .= "\n".$e->getMessage();
        }
		
		// merge feeds
		$this->mergeFeeds();

        // filter items to display
        $this->filterItems();
		
		// display the page
        if ($this->getRequest()->isXmlHttpRequest())
        {
            if (count($this->feed_data) == 0)
            {
                $this->error_msg .= "\nNincs több hír.";
            }

            $json = array(
                'maxtime' => $this->maxtime,
                'articles' => $this->feed_data,
                'error' => $this->error_msg,
            );

            $response = new Response(json_encode($json));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }
        else
        {
            return $this->render(
                'MSoRssReaderBundle:Default:index.html.twig',
                array(
                    'articles' => $this->feed_data,
                    'maxtime' => $this->maxtime
                )
            );
        }
    }
}
