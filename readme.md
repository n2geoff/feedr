# Feedr - A Simple Syndiction Aggregator for RSS

Smaller, faster library RSS reader that leverages SimpleXML for RSS XML processing

# USAGE

	//include library
	include 'feedr.php';

	//initialize with rss path
	$feed = new feedr('http://penne.dev.local/bonywordpress/?feed=rss2');

	//loop through items
	foreach($feed->items() as $item)
	{
		echo $item->content;
	}

## NOTE: _this is a work in progress_

