<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $avatar = [
          "/images/avatars/avatar-1.png",
            "/images/avatars/avatar-2.png",
            "/images/avatars/avatar-3.png",
            "/images/avatars/avatar-4.png",
            "/images/avatars/avatar-5.png",
            "/images/avatars/avatar-6.png",
        ];
        $users = [
            [
                "full_name" => 'Galasasen Slixby',
                "company" => 'Yotz PVT LTD',
                "role" => 'editor',
                "username" => 'gslixby0',
                "country" => 'El Salvador',
                "contact" => '(479) 232-9151',
                "email" => 'gslixby0@abc.net.au',
                "current_plan" => 'enterprise',
                "status" => 'inactive',
                "avatar" => $avatar[0],
                "billing" => 'Manual-Credit Card',
            ],
            [
                "full_name" => 'Halsey Redmore',
                "company" => 'Skinder PVT LTD',
                "role" => 'author',
                "username" => 'hredmore1',
                "country" => 'Albania',
                "contact" => '(472) 607-9137',
                "email" => 'hredmore1@imgur.com',
                "current_plan" => 'team',
                "status" => 'pending',
                "avatar" => $avatar[1],
                "billing" => 'Auto debit',
            ],
            [
                "full_name" => 'Marjory Sicely',
                "company" => 'Oozz PVT LTD',
                "role" => 'maintainer',
                "username" => 'msicely2',
                "country" => 'Russia',
                "contact" => '(321) 264-4599',
                "email" => 'msicely2@who.int',
                "current_plan" => 'enterprise',
                "status" => 'active',
                "avatar" => $avatar[2],
                "billing" => 'Manual-Credit Card',
            ],
            [
                "full_name" => 'Cyrill Risby',
                "company" => 'Oozz PVT LTD',
                "role" => 'maintainer',
                "username" => 'crisby3',
                "country" => 'China',
                "contact" => '(923) 690-6806',
                "email" => 'crisby3@wordpress.com',
                "current_plan" => 'team',
                "status" => 'inactive',
                "avatar" => $avatar[3],
                "billing" => 'Auto debit',
            ],
            [
                "full_name" => 'Maggy Hurran',
                "company" => 'Aimbo PVT LTD',
                "role" => 'subscriber',
                "username" => 'mhurran4',
                "country" => 'Pakistan',
                "contact" => '(669) 914-1078',
                "email" => 'mhurran4@yahoo.co.jp',
                "current_plan" => 'enterprise',
                "status" => 'pending',
                "avatar" => $avatar[4],
                "billing" => 'Manual-Cash',
            ],
            [
                "full_name" => 'Silvain Halstead',
                "company" => 'Jaxbean PVT LTD',
                "role" => 'author',
                "username" => 'shalstead5',
                "country" => 'China',
                "contact" => '(958) 973-3093',
                "email" => 'shalstead5@shinystat.com',
                "current_plan" => '"company"',
                "status" => 'active',
                "avatar" => $avatar[5],
                "billing" => 'Manual-Credit Card',
            ],
            [
                "full_name" => 'Breena Gallemore',
                "company" => 'Jazzy PVT LTD',
                "role" => 'subscriber',
                "username" => 'bgallemore6',
                "country" => 'Canada',
                "contact" => '(825) 977-8152',
                "email" => 'bgallemore6@boston.com',
                "current_plan" => '"company"',
                "status" => 'pending',
                "avatar" => $avatar[0],
                "billing" => 'Manual-PayPal',
            ],
            [
                "full_name" => 'Kathryne Liger',
                "company" => 'Pixoboo PVT LTD',
                "role" => 'author',
                "username" => 'kliger7',
                "country" => 'France',
                "contact" => '(187) 440-0934',
                "email" => 'kliger7@vinaora.com',
                "current_plan" => 'enterprise',
                "status" => 'pending',
                "avatar" => $avatar[1],
                "billing" => 'Auto debit',
            ],
            [
                "full_name" => 'Franz Scotfurth',
                "company" => 'Tekfly PVT LTD',
                "role" => 'subscriber',
                "username" => 'fscotfurth8',
                "country" => 'China',
                "contact" => '(978) 146-5443',
                "email" => 'fscotfurth8@dailymotion.com',
                "current_plan" => 'team',
                "status" => 'pending',
                "avatar" => $avatar[2],
                "billing" => 'Manual-Credit Card',
            ],
            [
                "full_name" => 'Jillene Bellany',
                "company" => 'Gigashots PVT LTD',
                "role" => 'maintainer',
                "username" => 'jbellany9',
                "country" => 'Jamaica',
                "contact" => '(589) 284-6732',
                "email" => 'jbellany9@kickstarter.com',
                "current_plan" => '"company"',
                "status" => 'inactive',
                "avatar" => $avatar[3],
                "billing" => 'Manual-Credit Card',
            ],
            [
                "full_name" => 'Jonah Wharlton',
                "company" => 'Eare PVT LTD',
                "role" => 'subscriber',
                "username" => 'jwharltona',
                "country" => 'United States',
                "contact" => '(176) 532-6824',
                "email" => 'jwharltona@oakley.com',
                "current_plan" => 'team',
                "status" => 'inactive',
                "avatar" => $avatar[4],
                "billing" => 'Manual-Cash',
            ],
            [
                "full_name" => 'Seth Hallam',
                "company" => 'Yakitri PVT LTD',
                "role" => 'subscriber',
                "username" => 'shallamb',
                "country" => 'Peru',
                "contact" => '(234) 464-0600',
                "email" => 'shallamb@hugedomains.com',
                "current_plan" => 'team',
                "status" => 'pending',
                "avatar" => $avatar[5],
                "billing" => 'Manual-PayPal',
            ],
            [
                "full_name" => 'Yoko Pottie',
                "company" => 'Leenti PVT LTD',
                "role" => 'subscriber',
                "username" => 'ypottiec',
                "country" => 'Philippines',
                "contact" => '(907) 284-5083',
                "email" => 'ypottiec@privacy.gov.au',
                "current_plan" => 'basic',
                "status" => 'inactive',
                "avatar" => $avatar[0],
                "billing" => 'Manual-PayPal',
            ],
            [
                "full_name" => 'Maximilianus Krause',
                "company" => 'Digitube PVT LTD',
                "role" => 'author',
                "username" => 'mkraused',
                "country" => 'Democratic Republic of the Congo',
                "contact" => '(167) 135-7392',
                "email" => 'mkraused@stanford.edu',
                "current_plan" => 'team',
                "status" => 'active',
                "avatar" => $avatar[1],
                "billing" => 'Manual-PayPal',
            ],
            [
                "full_name" => 'Zsazsa McCleverty',
                "company" => 'Kaymbo PVT LTD',
                "role" => 'maintainer',
                "username" => 'zmcclevertye',
                "country" => 'France',
                "contact" => '(317) 409-6565',
                "email" => 'zmcclevertye@soundcloud.com',
                "current_plan" => 'enterprise',
                "status" => 'active',
                "avatar" => $avatar[2],
                "billing" => 'Manual-PayPal',
            ],
            [
                "full_name" => 'Bentlee Emblin',
                "company" => 'Yambee PVT LTD',
                "role" => 'author',
                "username" => 'bemblinf',
                "country" => 'Spain',
                "contact" => '(590) 606-1056',
                "email" => 'bemblinf@wired.com',
                "current_plan" => '"company"',
                "status" => 'active',
                "avatar" => $avatar[3],
                "billing" => 'Manual-PayPal',
            ],
            [
                "full_name" => 'Brockie Myles',
                "company" => 'Wikivu PVT LTD',
                "role" => 'maintainer',
                "username" => 'bmylesg',
                "country" => 'Poland',
                "contact" => '(553) 225-9905',
                "email" => 'bmylesg@amazon.com',
                "current_plan" => 'basic',
                "status" => 'active',
                "avatar" => $avatar[4],
                "billing" => 'Manual-Credit Card',
            ],
            [
                "full_name" => 'Bertha Biner',
                "company" => 'Twinte PVT LTD',
                "role" => 'editor',
                "username" => 'bbinerh',
                "country" => 'Yemen',
                "contact" => '(901) 916-9287',
                "email" => 'bbinerh@mozilla.com',
                "current_plan" => 'team',
                "status" => 'active',
                "avatar" => $avatar[5],
                "billing" => 'Manual-Credit Card',
            ],
            [
                "full_name" => 'Travus Bruntjen',
                "company" => 'Cog"idoo PVT LTD',
                "role" => 'admin',
                "username" => 'tbruntjeni',
                "country" => 'France',
                "contact" => '(524) 586-6057',
                "email" => 'tbruntjeni@sitemeter.com',
                "current_plan" => 'enterprise',
                "status" => 'active',
                "avatar" => $avatar[0],
                "billing" => 'Manual-Cash',
            ],
            [
                "full_name" => 'Wesley Burland',
                "company" => 'Bubblemix PVT LTD',
                "role" => 'editor',
                "username" => 'wburlandj',
                "country" => 'Honduras',
                "contact" => '(569) 683-1292',
                "email" => 'wburlandj@uiuc.edu',
                "current_plan" => 'team',
                "status" => 'inactive',
                "avatar" => $avatar[1],
                "billing" => 'Manual-Cash',
            ],
            [
                "full_name" => 'Selina Kyle',
                "company" => 'Wayne Enterprises',
                "role" => 'admin',
                "username" => 'catwomen1940',
                "country" => 'USA',
                "contact" => '(829) 537-0057',
                "email" => 'irena.dubrovna@wayne.com',
                "current_plan" => 'team',
                "status" => 'active',
                "avatar" => $avatar[2],
                "billing" => 'Manual-Cash',
            ],
            [
                "full_name" => 'Jameson Lyster',
                "company" => 'Quaxo PVT LTD',
                "role" => 'editor',
                "username" => 'jlysterl',
                "country" => 'Ukraine',
                "contact" => '(593) 624-0222',
                "email" => 'jlysterl@guardian.co.uk',
                "current_plan" => '"company"',
                "status" => 'inactive',
                "avatar" => $avatar[3],
                "billing" => 'Manual-Cash',
            ],
            [
                "full_name" => 'Kare Skitterel',
                "company" => 'Ainyx PVT LTD',
                "role" => 'maintainer',
                "username" => 'kskitterelm',
                "country" => 'Poland',
                "contact" => '(254) 845-4107',
                "email" => 'kskitterelm@ainyx.com',
                "current_plan" => 'basic',
                "status" => 'pending',
                "avatar" => $avatar[4],
                "billing" => 'Manual-Credit Card',
            ],
            [
                "full_name" => 'Cleavland Hatherleigh',
                "company" => 'Flipopia PVT LTD',
                "role" => 'admin',
                "username" => 'chatherleighn',
                "country" => 'Brazil',
                "contact" => '(700) 783-7498',
                "email" => 'chatherleighn@washington.edu',
                "current_plan" => 'team',
                "status" => 'pending',
                "avatar" => $avatar[5],
                "billing" => 'Manual-Credit Card',
            ],
            [
                "full_name" => 'Adeline Micco',
                "company" => 'Topicware PVT LTD',
                "role" => 'admin',
                "username" => 'amiccoo',
                "country" => 'France',
                "contact" => '(227) 598-1841',
                "email" => 'amiccoo@whitehouse.gov',
                "current_plan" => 'enterprise',
                "status" => 'pending',
                "avatar" =>  $avatar[0],
                "billing" => 'Auto Debit',
            ],
            [
                "full_name" => 'Hugh Hasson',
                "company" => 'Skinix PVT LTD',
                "role" => 'admin',
                "username" => 'hhassonp',
                "country" => 'China',
                "contact" => '(582) 516-1324',
                "email" => 'hhassonp@bizjournals.com',
                "current_plan" => 'basic',
                "status" => 'inactive',
                "avatar" => "avatar6",
                "billing" => 'Auto Debit',
            ],
            [
                "full_name" => 'Germain Jacombs',
                "company" => 'Youopia PVT LTD',
                "role" => 'editor',
                "username" => 'gjacombsq',
                "country" => 'Zambia',
                "contact" => '(137) 467-5393',
                "email" => 'gjacombsq@jigsy.com',
                "current_plan" => 'enterprise',
                "status" => 'active',
                "avatar" => $avatar[0],
                "billing" => 'Auto Debit',
            ],
            [
                "full_name" => 'Bree Kilday',
                "company" => 'Jetpulse PVT LTD',
                "role" => 'maintainer',
                "username" => 'bkildayr',
                "country" => 'Portugal',
                "contact" => '(412) 476-0854',
                "email" => 'bkildayr@mashable.com',
                "current_plan" => 'team',
                "status" => 'active',
                "avatar" => $avatar[1],
                "billing" => 'Auto Debit',
            ],
            [
                "full_name" => 'Candice Pinyon',
                "company" => 'Kare PVT LTD',
                "role" => 'maintainer',
                "username" => 'cpinyons',
                "country" => 'Sweden',
                "contact" => '(170) 683-1520',
                "email" => 'cpinyons@behance.net',
                "current_plan" => 'team',
                "status" => 'active',
                "avatar" => $avatar[2],
                "billing" => 'Manual-Cash',
            ],
            [
                "full_name" => 'Isabel Mallindine',
                "company" => 'Voomm PVT LTD',
                "role" => 'subscriber',
                "username" => 'imallindinet',
                "country" => 'Slovenia',
                "contact" => '(332) 803-1983',
                "email" => 'imallindinet@shinystat.com',
                "current_plan" => 'team',
                "status" => 'pending',
                "avatar" => $avatar[3],
                "billing" => 'Manual-Cash',
            ],
            [
                "full_name" => 'Gwendolyn Meineken',
                "company" => 'Oyondu PVT LTD',
                "role" => 'admin',
                "username" => 'gmeinekenu',
                "country" => 'Moldova',
                "contact" => '(551) 379-7460',
                "email" => 'gmeinekenu@hc360.com',
                "current_plan" => 'basic',
                "status" => 'pending',
                "avatar" => $avatar[4],
                "billing" => 'Manual-Cash',
            ],
            [
                "full_name" => 'Rafaellle Snowball',
                "company" => 'Fivespan PVT LTD',
                "role" => 'editor',
                "username" => 'rsnowballv',
                "country" => 'Philippines',
                "contact" => '(974) 829-0911',
                "email" => 'rsnowballv@indiegogo.com',
                "current_plan" => 'basic',
                "status" => 'pending',
                "avatar" => $avatar[5],
                "billing" => 'Manual-Cash',
            ],
            [
                "full_name" => 'Rochette Emer',
                "company" => 'Thoughtworks PVT LTD',
                "role" => 'admin',
                "username" => 'remerw',
                "country" => 'North Korea',
                "contact" => '(841) 889-3339',
                "email" => 'remerw@blogtalkradio.com',
                "current_plan" => 'basic',
                "status" => 'active',
                "avatar" => $avatar[0],
                "billing" => 'Manual-Cash',
            ],
            [
                "full_name" => 'Ophelie Fibbens',
                "company" => 'Jaxbean PVT LTD',
                "role" => 'subscriber',
                "username" => 'ofibbensx',
                "country" => 'Indonesia',
                "contact" => '(764) 885-7351',
                "email" => 'ofibbensx@booking.com',
                "current_plan" => '"company"',
                "status" => 'active',
                "avatar" => $avatar[1],
                "billing" => 'Manual-Cash',
            ],
            [
                "full_name" => 'Stephen MacGilfoyle',
                "company" => 'Browseblab PVT LTD',
                "role" => 'maintainer',
                "username" => 'smacgilfoyley',
                "country" => 'Japan',
                "contact" => '(350) 589-8520',
                "email" => 'smacgilfoyley@bigcartel.com',
                "current_plan" => '"company"',
                "status" => 'pending',
                "avatar" => $avatar[2],
                "billing" => 'Manual-Cash',
            ],
            [
                "full_name" => 'Bradan Rosebotham',
                "company" => 'Agivu PVT LTD',
                "role" => 'subscriber',
                "username" => 'brosebothamz',
                "country" => 'Belarus',
                "contact" => '(882) 933-2180',
                "email" => 'brosebothamz@tripadvisor.com',
                "current_plan" => 'team',
                "status" => 'inactive',
                "avatar" => $avatar[3],
                "billing" => 'Manual-Credit Card',
            ],
            [
                "full_name" => 'Skip Hebblethwaite',
                "company" => 'Katz PVT LTD',
                "role" => 'admin',
                "username" => 'shebblethwaite10',
                "country" => 'Canada',
                "contact" => '(610) 343-1024',
                "email" => 'shebblethwaite10@arizona.edu',
                "current_plan" => '"company"',
                "status" => 'inactive',
                "avatar" => $avatar[4],
                "billing" => 'Manual-Credit Card',
            ],
            [
                "full_name" => 'Moritz Piccard',
                "company" => 'Twitternation PVT LTD',
                "role" => 'maintainer',
                "username" => 'mpiccard11',
                "country" => 'Croatia',
                "contact" => '(365) 277-2986',
                "email" => 'mpiccard11@vimeo.com',
                "current_plan" => 'enterprise',
                "status" => 'inactive',
                "avatar" => $avatar[5],
                "billing" => 'Manual-Credit Card',
            ],
            [
                "full_name" => 'Tyne W"idmore',
                "company" => 'Yombu PVT LTD',
                "role" => 'subscriber',
                "username" => 'tw"idmore12',
                "country" => 'Finland',
                "contact" => '(531) 731-0928',
                "email" => 'tw"idmore12@bravesites.com',
                "current_plan" => 'team',
                "status" => 'pending',
                "avatar" => $avatar[0],
                "billing" => 'Auto Debit',
            ],
            [
                "full_name" => 'Florenza Desporte',
                "company" => 'Kamba PVT LTD',
                "role" => 'author',
                "username" => 'fdesporte13',
                "country" => 'Ukraine',
                "contact" => '(312) 104-2638',
                "email" => 'fdesporte13@omniture.com',
                "current_plan" => '"company"',
                "status" => 'active',
                "avatar" => $avatar[1],
                "billing" => 'Auto Debit',
            ],
            [
                "full_name" => 'Edwina Baldetti',
                "company" => 'Dazzlesphere PVT LTD',
                "role" => 'maintainer',
                "username" => 'ebaldetti14',
                "country" => 'Haiti',
                "contact" => '(315) 329-3578',
                "email" => 'ebaldetti14@theguardian.com',
                "current_plan" => 'team',
                "status" => 'pending',
                "avatar" => $avatar[2],
                "billing" => 'Auto Debit',
            ],
            [
                "full_name" => 'Benedetto Rossiter',
                "company" => 'Mybuzz PVT LTD',
                "role" => 'editor',
                "username" => 'brossiter15',
                "country" => 'Indonesia',
                "contact" => '(323) 175-6741',
                "email" => 'brossiter15@craigslist.org',
                "current_plan" => 'team',
                "status" => 'inactive',
                "avatar" => $avatar[3],
                "billing" => 'Auto Debit',
            ],
            [
                "full_name" => 'Micaela McNirlan',
                "company" => 'Tambee PVT LTD',
                "role" => 'admin',
                "username" => 'mmcnirlan16',
                "country" => 'Indonesia',
                "contact" => '(242) 952-0916',
                "email" => 'mmcnirlan16@hc360.com',
                "current_plan" => 'basic',
                "status" => 'inactive',
                "avatar" => $avatar[4],
                "billing" => 'Auto Debit',
            ],
            [
                "full_name" => 'Vladamir Koschek',
                "company" => 'Centimia PVT LTD',
                "role" => 'author',
                "username" => 'vkoschek17',
                "country" => 'Guatemala',
                "contact" => '(531) 758-8335',
                "email" => 'vkoschek17@abc.net.au',
                "current_plan" => 'team',
                "status" => 'active',
                "avatar" => $avatar[5],
                "billing" => 'Manual-Credit Card',
            ],
            [
                "full_name" => 'Corrie Perot',
                "company" => 'Flipopia PVT LTD',
                "role" => 'subscriber',
                "username" => 'cperot18',
                "country" => 'China',
                "contact" => '(659) 385-6808',
                "email" => 'cperot18@goo.ne.jp',
                "current_plan" => 'team',
                "status" => 'pending',
                "avatar" => $avatar[0],
                "billing" => 'Manual-Credit Card',
            ],
            [
                "full_name" => 'Saunder Offner',
                "company" => 'Skalith PVT LTD',
                "role" => 'maintainer',
                "username" => 'soffner19',
                "country" => 'Poland',
                "contact" => '(200) 586-2264',
                "email" => 'soffner19@mac.com',
                "current_plan" => 'enterprise',
                "status" => 'pending',
                "avatar" => $avatar[1],
                "billing" => 'Manual-Credit Card',
            ],
            [
                "full_name" => 'Karena Courtliff',
                "company" => 'Feedfire PVT LTD',
                "role" => 'admin',
                "username" => 'kcourtliff1a',
                "country" => 'China',
                "contact" => '(478) 199-0020',
                "email" => 'kcourtliff1a@bbc.co.uk',
                "current_plan" => 'basic',
                "status" => 'active',
                "avatar" => $avatar[2],
                "billing" => 'Manual-Credit Card',
            ],
            [
                "full_name" => 'Onfre Wind',
                "company" => 'Thoughtmix PVT LTD',
                "role" => 'admin',
                "username" => 'owind1b',
                "country" => 'Ukraine',
                "contact" => '(344) 262-7270',
                "email" => 'owind1b@yandex.ru',
                "current_plan" => 'basic',
                "status" => 'pending',
                "avatar" => $avatar[3],
                "billing" => 'Manual-PayPal',
            ],
            [
                "full_name" => 'Paulie Durber',
                "company" => 'Babbleblab PVT LTD',
                "role" => 'subscriber',
                "username" => 'pdurber1c',
                "country" => 'Sweden',
                "contact" => '(694) 676-1275',
                "email" => 'pdurber1c@gov.uk',

                "current_plan" => 'team',
                "status" => 'inactive',
                "avatar" => $avatar[4],
                "billing" => 'Manual-PayPal',
            ],
            [
                "full_name" => 'Beverlie Krabbe',
                "company" => 'Kaymbo PVT LTD',
                "role" => 'editor',
                "username" => 'bkrabbe1d',
                "country" => 'China',
                "contact" => '(397) 294-5153',
                "email" => 'bkrabbe1d@home.pl',
                "current_plan" => '"company"',
                "status" => 'active',
                "avatar" => $avatar[5],
                "billing" => 'Manual-Cash',
            ],
        ];

        User::insert($users);
    }
}
