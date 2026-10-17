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
/** Ниво заштите: 'strict' = осетљива врста (орхидеје, СЗ, издвојене ретке),
 *  'unclassified' = биљка ван провереног списка 109 или изнад нивоа врсте (само без координата),
 *  'none' = јавно. */
function rajac_protection_level(array $r): string {
    if(($r['grupa_taxon']??'')!=='Plantae')return 'none';
    $p=rajac_policy();$scientific=trim((string)($r['naucni']??''));
    $genus=explode(' ',$scientific)[0];
    if((int)($r['family_orchidaceae']??0)===1 || $scientific==='Orchidaceae' || in_array($genus,$p['orchid_genera']??[],true))return 'strict';
    if(in_array($scientific,$p['extra_sensitive_names']??[],true))return 'strict';
    $approved=false;
    foreach(($p['species']??[]) as $slug=>$v){
        $match=(($v['scientific_name']??'')===$scientific || ($v['taxon_query']??'')===$scientific ||
           (!empty($v['taxon_id']) && (int)$v['taxon_id']===(int)($r['taxon_id']??0)));
        if(!$match)continue;
        if(!empty($v['hide_public_coordinates']))return 'strict';
        $approved=true;
    }
    // Биљке изнад нивоа врсте могу бити заштићени таксони: без тачке.
    if(!in_array((string)($r['rank']??''),['species','subspecies','variety'],true))return 'unclassified';
    // Врсте ван провереног списка чекају преглед управљача: без тачке.
    return $approved?'none':'unclassified';
}
function rajac_sensitive_observation(array $r): bool { return rajac_protection_level($r)!=='none'; }
function rajac_public_observation(array $r): array {
    $level=rajac_protection_level($r);
    $strict=$level==='strict';
    // Стари кеш нема метаподатке: координате се не проглашавају тачним.
    $hasMeta=array_key_exists('coordinates_obscured',$r);
    $obscured=!$hasMeta || (int)($r['coordinates_obscured']??1)===1 ||
       in_array((string)($r['geoprivacy']??''),['private','obscured'],true) ||
       in_array((string)($r['taxon_geoprivacy']??''),['private','obscured'],true);
    $hide=$level!=='none' || $obscured;
    $r['location_hidden']=$hide;
    $r['location_label']=$level!=='none'?'Тачне локације нису приказане ради заштите природе':
      ($obscured?'Локација је приближна или непроверена':'Јавно доступна локација');
    if($hide){$r['lat']=null;$r['lng']=null;$r['positional_accuracy']=null;}
    if($strict){
        // Без података из којих се може реконструисати појединачни налаз на iNaturalist-у.
        $r['obs_id']=null;$r['url']=null;$r['foto_url']='';$r['photo_source']='none';
        $r['korisnik']='';$r['datum']=substr((string)($r['datum']??''),0,4);
    }
    unset($r['family_orchidaceae'],$r['coordinates_obscured'],$r['geoprivacy'],$r['taxon_geoprivacy']);
    return $r;
}
