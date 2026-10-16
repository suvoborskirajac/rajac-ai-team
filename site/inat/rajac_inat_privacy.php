<?php
/** Single data minimization policy for /inat. PHP 7.4+. Never serve restricted coordinates. */
declare(strict_types=1);
function rajac_policy(): array {
    static $policy = null;
    if ($policy === null) {
        $p = '/home/piorajac/public_html/inat/species_privacy.json';
        if (!is_file($p)) throw new RuntimeException('Privacy policy missing (fail-closed).');
        $obj=json_decode((string)file_get_contents($p),true);
        if (!is_array($obj)||!isset($obj['species']))throw new RuntimeException('Invalid privacy policy');
        $policy=$obj;
    }
    return $policy;
}
function rajac_orchid_genera(): array { return rajac_policy()['orchid_genera']??[]; }
function rajac_is_orchid_taxon(array $taxon): bool {
    $nm=trim((string)($taxon['name']??''));$genus=explode(' ',$nm)[0];
    if($nm==='Orchidaceae'||in_array($genus,rajac_orchid_genera(),true))return true;
    // Taxon ancestor names from v1 API if available, rather than guessing from name only.
    foreach(($taxon['ancestors']??[]) as $a)if(($a['name']??'')==='Orchidaceae')return true;
    return false;
}
function rajac_sensitive_observation(array $r): bool {
    if(($r['grupa_taxon']??'')!=='Plantae')return false;
    $p=rajac_policy();$scientific=trim((string)($r['naucni']??''));
    $genus=explode(' ',$scientific)[0];
    if((int)($r['family_orchidaceae']??0)===1 || $scientific==='Orchidaceae' || in_array($genus,$p['orchid_genera']??[],true))return true;
    $approved=false;
    foreach(($p['species']??[]) as $slug=>$v){
        $match=(($v['scientific_name']??'')===$scientific || ($v['taxon_query']??'')===$scientific ||
           (!empty($v['taxon_id']) && (int)$v['taxon_id']===(int)($r['taxon_id']??0)));
        if(!$match)continue;
        if(!empty($v['hide_public_coordinates']))return true;
        $approved=true;
    }
    if(in_array($scientific,$p['extra_sensitive_names']??[],true))return true;
    // Higher-rank unidentified plants may include protected taxa (do not expose pinpoint).
    if(!in_array((string)($r['rank']??''),['species','subspecies','variety'],true))return true;
    // Species absent from 109-entry policy are pending a manager review: fail closed.
    return !$approved;
}
function rajac_public_observation(array $r): array {
    $sensitive=rajac_sensitive_observation($r);
    // Old cache has no metadata; never certify old coordinates as exact. We still can hide sensitive names.
    $hasMeta=array_key_exists('coordinates_obscured',$r);
    $obscured=!$hasMeta || (int)($r['coordinates_obscured']??1)===1 ||
       in_array((string)($r['geoprivacy']??''),['private','obscured'],true) ||
       in_array((string)($r['taxon_geoprivacy']??''),['private','obscured'],true);
    $hide=$sensitive || $obscured;
    $r['location_hidden']=$hide;
    $r['location_label']=$sensitive?'Тачне локације нису приказане ради заштите природе':
      ($obscured?'Локација је приближна или непроверена':'Јавно доступна локација');
    if($hide){
        $r['lat']=null;$r['lng']=null;
        $r['positional_accuracy']=null;
        // Do not offer a direct deep-link from the public aggregator to exact sensitive sites.
        if($sensitive)$r['url']='https://www.inaturalist.org/observations';
    }
    unset($r['family_orchidaceae']);
    return $r;
}
