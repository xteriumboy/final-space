<?php
function ls_rate_limit($key, $limit=30, $window=60){
  $f=sys_get_temp_dir().'/ls_rl_'.md5($key); $now=time(); $d=['ts'=>$now,'hits'=>0];
  if(is_file($f)) $d=json_decode(@file_get_contents($f),true) ?: $d;
  if(($now-($d['ts']??0))>$window) $d=['ts'=>$now,'hits'=>0];
  $d['hits']=($d['hits']??0)+1; file_put_contents($f,json_encode($d));
  if($d['hits']>$limit){ http_response_code(429); header('Retry-After: '.max(1,$window-($now-($d['ts']??$now)))); echo 'rate_limited'; exit; }
}
function ls_log($c,$m){ @mkdir(__DIR__.'/../data',0775,true); file_put_contents(__DIR__.'/../data/server.log',date('c')." [$c] $m
",FILE_APPEND); }
