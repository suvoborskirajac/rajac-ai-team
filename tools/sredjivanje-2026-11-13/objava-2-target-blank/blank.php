<?php
// Привремена скрипта (Claude, 13.11.2026): уклањање target="_blank" (линкови у истом прозору). Брише се после употребе.
header('Content-Type: text/plain; charset=utf-8');
if (!isset($_GET['k']) || !hash_equals('__KLJUC__', $_GET['k'])) { http_response_code(404); exit; }
@set_time_limit(300);
$ROOT = __DIR__; $apply = !empty($_GET['apply']);
$bk = dirname($ROOT) . '/_rezerva_blank_20261113';
$LIST = <<<'TXT'
biljke/achillea-millefolium-l/index.html|85c4a635d60cb2225e5d|4eedaaff96eeab7d848a|7
biljke/allium-carinatum-l/index.html|60079652d191cc2f2cae|76ca747fdca369f5655a|5
biljke/allium-ursinum-l/index.html|34f9285c6690ad89746e|18619be832e583b7737b|7
biljke/anthyllis-vulneraria-l/index.html|0c1aa2426ab1092187cf|ba08d12fc0194183a2ac|7
biljke/cornus-mas-l/index.html|8b25d1f843b02d724380|a29bf3038d6216c0aa0e|9
biljke/corylus-avellana-l/index.html|347f82d29c9fe9109fb8|398078ce5ed7133717cf|9
biljke/corylus-colurna-l/index.html|48bba4994e4838d422cf|c33db97221767fbdfb96|6
biljke/crataegus-monogyna-jacq/index.html|9ee926d3e8f94b26b8da|ba954545444270407f5e|10
biljke/daucus-carota-l/index.html|5971c091a641905b07bd|35d924c9940529c40c2f|5
biljke/dianthus-carthusianorum-l/index.html|2d423e026a8c41088c0e|ce28c0a6f9c8368d4a28|5
biljke/erythronium-dens-canis-l/index.html|bf8eac822b81a485f400|f78ddeab14ddf7e42a96|5
biljke/festuca-arundinacea-schreb/index.html|a6cfa1b927af014f1b8f|63cf573827af9762cb14|4
biljke/lactuca-perennis-l/index.html|6753948ddc8de4b828dd|0b630539f8246ee98928|4
biljke/linaria-vulgaris/index.html|7fb77569b5176ada2c20|3ab20cbaab81bb970d77|5
biljke/malus-sylvestris-l-mill/index.html|1affaf5ffa498ff7e09e|0baeccf8cdb7cbdf5390|7
biljke/primula-veris/index.html|3d562415728f366d4490|6e6b0b0a75666272e70c|8
digitalni-centar/vizualizacije/svetlosno-zagadjenje-grafikon/index.html|dd146422aa72f98ddb02|8ea24212085c4e65fdb5|4
gljive/amanita-caesarea/index.html|0595bcc4266e22948325|f79e79e20fdea44f56be|8
gljive/amanita-muscaria/index.html|da6f66b68f9d952f31b7|85492b8ffefcc3ce1037|9
gljive/amanita-phalloides/index.html|49be21ddc3b26e19cbf6|dbd87466021c1d1b9a71|8
gljive/amanita-rubescens/index.html|3c8685cf6986b762358f|ff75c560db6759bd99ff|7
gljive/amanita-vaginata/index.html|b8c149e2d36abc157edf|035c81c81f97b4c6c25d|7
gljive/armillaria-mellea/index.html|96fc60454e1376e5c25d|39262f327def6a20cbf9|9
gljive/boletus-edulis/index.html|666c76315da9b387d276|5458929a9da787457a53|7
gljive/cantharellus-cibarius/index.html|03e387ef61694be333b1|690acb5218e95041bd76|8
gljive/coprinus-comatus/index.html|ccda5d2170c07a25e156|dc84eb087d6611ddd2fe|7
gljive/craterellus-cornucopioides/index.html|6fdc2c5c94089cd1281e|4ceccb62d668198ec723|7
gljive/fistulina-hepatica/index.html|c6255cd58eb0d5d26658|633da2424ccc79a01b67|7
gljive/ganoderma-lucidum/index.html|afd41eb232cacd81a0a5|56dc7d8fa026a0f43557|10
gljive/hypholoma-fasciculare/index.html|0f3d1120f67364e661bd|a2c21a958a1d44ddb019|9
gljive/lactarius-deliciosus/index.html|bbf473ca78bb2d9369de|2cd1124849c50a3cb159|7
gljive/lycoperdon-perlatum/index.html|387317c64993bc432ade|6b8d627b4b82a489973d|10
gljive/macrolepiota-procera/index.html|8fe6e3b1bc63fd6d1c6b|a961899cbb9f663b2c6d|7
gljive/marasmius-oreades/index.html|8bd8560c8b5fbea820df|2eda7004bc18269e5b76|8
gljive/morchella-esculenta/index.html|4848c19d43d7753d1b2a|fa3dde64e3ab77160bcf|10
gljive/pholiota-squarrosa/index.html|b6bc7b5efcee687d8c12|6e1e1d3c54251c056200|6
gljive/pleurotus-ostreatus/index.html|7d9d6a9030a7a70f17ee|1764a10fd750027eb199|6
gljive/rubroboletus-satanas/index.html|512e339335b3b96a2f80|4a880e39bf1c5cf50f34|6
gljive/russula-cyanoxantha/index.html|799da3f4a831eedeed06|2a85b481444b9fa19b4e|8
gljive/russula-virescens/index.html|2830f1281ac6b954cbfc|502763c09a321d14714d|8
gljive/suillus-luteus/index.html|51da833e2a18c3d17b94|a7dad520732ad4fdaafd|6
gljive/trametes-versicolor/index.html|b984ace20b4a1e941f59|4b9f8c3382fa3d565dc3|9
planiranje-posete/index.html|176e4162dbf6e54f53c4|1882a0196c7a93597172|3
rajac-astronomija/index.html|cfedd299b86ed072722b|4b909fc75fe96bc594f4|1
rajac-mreza/index.html|bfe256ee3a159924a99c|c7d21c6e65e8bd33552f|2
rajac-vesti/lekovite-biljke-rajca/index.html|dc885d003b8a18170f6c|d9acd4cf08e170ca6dd1|18
sisari/apodemus-agrarius/index.html|6ac25dcdd3d0e219081a|bcf58d21678dc0b04117|4
sisari/apodemus-flavicollis/index.html|75bc8dfcdf1f5297d388|5e13c33a394cc6c9aa12|5
sisari/apodemus-sylvaticus/index.html|274920517d7863545d9f|58e298966f1304934508|4
sisari/barbastella-barbastellus/index.html|ee6fe3d1bbafbbeb08a6|b088d9a70706f44867e0|10
sisari/canis-aureus/index.html|6fd453ac3f934fd67c47|45a7bd9587e336361916|5
sisari/canis-lupus/index.html|ac58c2a6bf19255603fc|271a7c3c0597fa439065|5
sisari/capreolus-capreolus/index.html|5bbec1569fd7a718173c|ad2581e0f24de03c4474|5
sisari/crocidura-leucodon/index.html|99c5b178fa59613d75a6|0fe7950e07bd2f9bc334|4
sisari/crocidura-suaveolens/index.html|592ec749d9f611f9a602|dffaed226dae3f6b60a8|4
sisari/eptesicus-serotinus/index.html|8d1b7646dbaefbf830b3|dc2acb0731cf825aaf86|10
sisari/erinaceus-roumanicus/index.html|227c35d62e4bcf4b728c|b10ea3fc45f5a55e9c97|4
sisari/felis-silvestris/index.html|bfb949cb7936f5b772b3|2f131c59127bd61871d6|5
sisari/glis-glis/index.html|d44fdfd579ceb3694500|afa72e8096a6cd3e6cad|5
sisari/lepus-europaeus/index.html|ee4bdd20ee5fdb453ee6|6c09eb6defaabdeed26d|5
sisari/lutra-lutra/index.html|c74ff45dafaae2cdc41a|d2eae3cbc67a3b17bc83|4
sisari/martes-foina/index.html|27ab9fcffd21d37b8ce3|dc1344a1c9cfda92b16e|5
sisari/martes-martes/index.html|839b034362c3f514a1f3|58d1a0d9ac6a937fc338|5
sisari/meles-meles/index.html|4c2e1edaf9aebb52c046|a87bc7fe01300a76a626|5
sisari/microtus-arvalis/index.html|730baa116fdc0d75dac8|34f9bc26426d6411cb90|5
sisari/microtus-subterraneus/index.html|780102b196104d06f958|91fb96a83b40b5cd07ed|4
sisari/miniopterus-schreibersii/index.html|20cb35877f16aee0d494|fe18420f4024a8c0b384|10
sisari/muscardinus-avellanarius/index.html|437b23df1a9e1183ce60|1b9a37ef64b21d8aa319|4
sisari/mustela-nivalis/index.html|da31255a0d355971a451|4f8fb87abf2344745588|5
sisari/mustela-putorius/index.html|26a614ad9d3349a331d1|8819422b6ea170e057af|5
sisari/myodes-glareolus/index.html|2997c9cb6fc4a8d11a7c|08fb1d042131c4a11021|5
sisari/myotis-bechsteinii/index.html|e7f5cac93d0d470721e5|c31097f879e170f2cdbf|10
sisari/myotis-blythii/index.html|16d29b9efb81ed09dd5f|964f325385d1b8c5ad30|10
sisari/myotis-capaccinii/index.html|8b872c30678ee5b758e2|f4cef3f19da3d0108722|10
sisari/myotis-daubentonii/index.html|d61270d4255baeab9872|3dd778abd7f70ef37ed2|10
sisari/myotis-emarginatus/index.html|e2de16d6f1e0f36e6398|fc1cc55c772a95143206|10
sisari/myotis-myotis/index.html|e3697976879ddae37c3a|8a4ac6354ac4e3ae845c|10
sisari/myotis-mystacinus/index.html|50d950bcb1a960d3b16b|f4a8e89c053a7a3a93ed|10
sisari/myotis-nattereri/index.html|82e3f75fc7f3a11b4fda|8fbcd0b41db649f228af|10
sisari/nannospalax-leucodon/index.html|68cf60e4f3020095eebf|f3fe99232712db11d673|5
sisari/neomys-fodiens/index.html|3ab8443a26b674bd9e5f|ddcfcf495bbe332c5d90|4
sisari/nyctalus-noctula/index.html|9608163d4a3406591e49|458e314cbd42e8eb40f4|10
sisari/pipistrellus-pipistrellus/index.html|63b0430f82b81358cb7c|39d854c6d327f87a2b74|10
sisari/plecotus-auritus/index.html|fb5bec2cd4d259740df8|bc218d82dd4e76a06cb0|11
sisari/plecotus-austriacus/index.html|d734f4da9d8146460806|1c775835afd3972ca56c|10
sisari/rhinolophus-blasii/index.html|cb0456730ecabb541adb|4f52c21386947cebe7a0|10
sisari/rhinolophus-euryale/index.html|8541dc65219562b0d2ae|e7d00738b311cd8cdaa7|10
sisari/rhinolophus-ferrumequinum/index.html|7de28fc6d0328e60a508|2ca5a867a8636caf756e|10
sisari/rhinolophus-hipposideros/index.html|69cd9bad1c1ae8ff6731|7141c196d793adc4bb8f|10
sisari/sciurus-vulgaris/index.html|68eba411f675ecd1fe3c|01943073d4e44b141128|5
sisari/sorex-araneus/index.html|a241fa15850c5fe9db72|f83a901646dbf325515a|4
sisari/sorex-minutus/index.html|c2e2a3f63faeb0a18988|deeb5682ec400cc97b3b|4
sisari/sus-scrofa/index.html|7eccb6b18cd199c7baf5|c66f9642676c7804e037|6
sisari/talpa-europaea/index.html|df9885babc74f0ed1295|d4b82c6169ab80dac901|4
sisari/ursus-arctos/index.html|dfb5cdb6415eeeab9fdc|82383e74ccae0ddee2a5|4
sisari/vulpes-vulpes/index.html|700d385bad6443ec727b|6545f0c169656e453d31|5
trofodinamika/1_producers.html|5ef7cf72a2a997d8cb3a|209e32844cc8a7cfe36c|6
trofodinamika/2_consumers.html|94523a9bbd6b65df16da|e05b6e6a714abfebb278|6
trofodinamika/3_pollination.html|046f0333a54f7913f2bf|722c95659ea5cab246ca|7
trofodinamika/4_decomposers_threats.html|61833925471b085e1ff4|21c1d0582d631ccfe7fa|6
trofodinamika/index.html|0f1dcb2fe8ac332ca4f7|1c4648040bb7896a5902|7
trofodinamika/kviz.html|8f969e94ec78341a89df|6b021c707f70005046b2|5
trofodinamika/vrsta/abies-alba.html|38984f02b6f2dfbf82fe|2c9aee729967efdaa506|2
trofodinamika/vrsta/accipiter-nisus.html|51daa223eee16abbe291|dc13cf416202f0d746fb|1
trofodinamika/vrsta/agrostis-festuca.html|d258648c4b7424af0a1f|a268662b7b8885362f43|2
trofodinamika/vrsta/anacamptis.html|d776ce1f79576dd1baa6|95f7daf56e3357510c94|3
trofodinamika/vrsta/aphididae.html|dc2c6e186480df3d34f4|124ec868f6ccba8b4aa1|2
trofodinamika/vrsta/apis.html|311267539a7b0d0ffc81|e3108b570cd2d0465055|2
trofodinamika/vrsta/buteo-buteo.html|edbab3bdabf5639b6515|c891b02fb36e192c849d|1
trofodinamika/vrsta/butterfly.html|931c8dcc31c8320ef19b|5206b75e72a24caae726|2
trofodinamika/vrsta/canis-aureus.html|95cd6434495904f62c9f|9029a9025e02ac54983f|1
trofodinamika/vrsta/canis-lupus.html|78a27777ed99c35a1a50|c2e5b7aac5279c8c2aef|3
trofodinamika/vrsta/capreolus-capreolus.html|15b481b977b072174e0c|a4f39b1ddc6f5d777022|1
trofodinamika/vrsta/carduelis.html|12ec904dabdbea833cb3|55d1e1a361c330010eba|1
trofodinamika/vrsta/caterpillars.html|b0b44f3e1d893b9c7d93|68e21fdd2ab45ebabfed|2
trofodinamika/vrsta/fagus-moesiaca.html|51c4c0b4b206ddba91aa|82b83c3f4d0081fbfa8c|2
trofodinamika/vrsta/fringilla.html|1bb0560c51b15e24c445|a0845867c92c8f9d60de|1
trofodinamika/vrsta/lepus-europaeus.html|412d607b3a14e4b9dd74|e5dae112da571290980d|2
trofodinamika/vrsta/moths.html|977bd84f37d7c9e73425|06b090ce6989059ec451|3
trofodinamika/vrsta/mushroom.html|f5ef7dd4029451b8d198|5d58d13b581e00a8bf50|3
trofodinamika/vrsta/platanthera.html|267540e0f7e9f64f7fcf|3318f2e0c71a11ba92d4|4
trofodinamika/vrsta/quercus.html|e1e8dcbca4b4268573d2|a8a9a5fffc9090434ac1|3
trofodinamika/vrsta/sciurus-vulgaris.html|00db00adf5cc4bdc8601|0b8b7b73f526a0920a12|1
trofodinamika/vrsta/sitta.html|7cc9c2ac19d30380dc39|454dc3452c5c60854139|1
trofodinamika/vrsta/strix-aluco.html|cd1968fc0e31f6690788|f0dc4e019c7d51366071|1
trofodinamika/vrsta/sus-scrofa.html|2e89fe1ab8af29abfaca|919458cdece134b6e5d1|1
trofodinamika/vrsta/syrphidae.html|479eeabd62161d4286d7|b698990c78f2570bd4c4|2
trofodinamika/vrsta/vulpes-vulpes.html|800fb05b0b48b98eb979|901738990372130d0029|2
trofodinamika/vrsta/yarrow.html|9e007eaf204b58f59efe|899226a438751a91df22|1
za-vlasnike/satelit/index.html|23c40109ff2166c3ce3d|540e9605f3724e14538f|1
TXT;
$ok = 0; $done = 0; $skip = []; $links = 0;
foreach (explode("\n", trim($LIST)) as $row) {
    [$p, $before, $after, $n] = explode('|', $row);
    $f = "$ROOT/$p";
    $s = @file_get_contents($f);
    if ($s === false) { $skip[] = "НЕМА $p"; continue; }
    if (substr(hash('sha256', $s),0,20) === $after) { $ok++; continue; }
    if (substr(hash('sha256', $s),0,20) !== $before) { $skip[] = "ПРОМЕЊЕН НА СЕРВЕРУ, прескочено: $p"; continue; }
    $new = preg_replace('~\s+target\s*=\s*(["\'])_blank\1~i', '', $s, -1, $cnt);
    if ($cnt != (int)$n || substr(hash('sha256', $new),0,20) !== $after) { $skip[] = "НЕ ПОКЛАПА СЕ, прескочено: $p"; continue; }
    $ok++; $links += $cnt;
    if (!$apply) continue;
    @mkdir(dirname("$bk/$p"), 0755, true);
    if (!is_file("$bk/$p") && !copy($f, "$bk/$p")) { $skip[] = "РЕЗЕРВА НИЈЕ УСПЕЛА, прескочено: $p"; $ok--; continue; }
    $w = @file_put_contents($f, $new, LOCK_EX);
    if ($w !== strlen($new) || substr(hash_file('sha256', $f),0,20) !== $after) { $skip[] = "УПИС НИЈЕ УСПЕО: $p " . json_encode(error_get_last(), JSON_UNESCAPED_UNICODE); $ok--; continue; }
    $done++;
}
echo ($apply ? "УПИС" : "ПРОВЕРА") . ": исправних/спремних $ok од " . count(explode("\n", trim($LIST))) . ", уписано $done, уклоњених target=_blank $links\n";
if ($apply) echo "Резерва: $bk\n";
echo implode("\n", $skip) . "\n";
