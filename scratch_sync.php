$p=App\Models\Polda::where("code","SULBAR")->first(); $feeds=App\Models\MediaFeed::where("polda_id",$p->id)->get();
$items=(new App\Services\RssFeedService)->fetchAggregatedFeeds($feeds->toArray(), true);
foreach(array_slice($items,0,400) as $i){ App\Models\MediaArticle::updateOrCreate(["url"=>$i["link"]],["polda_id"=>$p->id,"feed_id"=>$i["source_id"]?:null,"title"=>$i["title"],"source_name"=>strtoupper($i["source_name"]),"category"=>$i["category"],"image_url"=>$i["image_url"]?:null,"summary"=>$i["summary"],"pub_date"=>!empty($i["pub_date"])?date("Y-m-d H:i:s",strtotime($i["pub_date"])):now(),"sentiment"=>$i["sentiment"]??"netral"]); }
echo "articles=".App\Models\MediaArticle::count();
