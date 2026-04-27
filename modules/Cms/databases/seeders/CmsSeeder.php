<?php

declare(strict_types=1);

namespace Modules\Cms\databases\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Cms\Models\FaqItem;
use Modules\Cms\Models\SiteSetting;
use Modules\Cms\Models\Testimonial;

/**
 * Seeds initial CMS content matching the frontend hardcoded data (2026-04-22).
 * Idempotent — safe to re-run.
 */
class CmsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedSiteSettings();
            $this->seedFaq();
            $this->seedTestimonials();
        });
    }

    private function seedSiteSettings(): void
    {
        SiteSetting::updateOrCreate(
            ['id' => 1],
            [
                'contact_email' => 'kontakt@rosadoro.pl',
                'contact_phone' => '+48 XXX XXX XXX',
                'contact_phone_href' => 'tel:+48000000000',
                'contact_address' => 'Warszawa, Polska',
                'business_hours' => 'Pn–Pt 9:00–17:00',
                'social_facebook' => null,
                'social_instagram' => null,
                'social_linkedin' => null,
                'hero_video_url' => 'https://test-videos.co.uk/vids/bigbuckbunny/mp4/h264/1080/Big_Buck_Bunny_1080_10s_2MB.mp4',
            ],
        );
    }

    private function seedFaq(): void
    {
        $items = [
            [
                'slug' => 'how-to-buy',
                'question' => 'Jak wygląda proces zakupu?',
                'answer' => 'Wybierasz gotowy box z jednego z pakietów (Standard, Premium, VIP) i dodajesz do koszyka. Po opłaceniu online skontaktujemy się z Tobą w ciągu 24 godzin, aby ustalić adres dostawy, dedykację i preferowany termin.',
                'category' => 'purchase',
            ],
            [
                'slug' => 'delivery',
                'question' => 'Ile trwa dostawa?',
                'answer' => 'Po ustaleniu szczegółów paczkę wysyłamy kurierem — dostarczamy w 1-2 dni robocze na terenie Polski. Koszt dostawy jest wliczony w cenę boxu.',
                'category' => 'shipping',
            ],
            [
                'slug' => 'payment',
                'question' => 'Jakie formy płatności akceptujecie?',
                'answer' => 'Płatności online obsługuje Przelewy24 — możesz zapłacić BLIK-iem, kartą lub szybkim przelewem bankowym. Potwierdzenie wraz z fakturą otrzymasz od razu po opłaceniu.',
                'category' => 'payment',
            ],
            [
                'slug' => 'invoice',
                'question' => 'Czy otrzymam fakturę?',
                'answer' => 'Tak, do każdego zamówienia wystawiamy fakturę — w trakcie zakupu wybierasz czy jest ona na osobę prywatną, czy na firmę (wtedy potrzebujemy NIP).',
                'category' => 'payment',
            ],
            [
                'slug' => 'dedication',
                'question' => 'Czy mogę dołączyć dedykację?',
                'answer' => 'Oczywiście. Dedykację uzgodnimy z Tobą telefonicznie lub mailowo po zakupie — możesz też wpisać ją od razu w polu „Uwagi" w trakcie zamówienia.',
                'category' => 'personalization',
            ],
            [
                'slug' => 'return',
                'question' => 'Co jeśli box się nie spodoba?',
                'answer' => 'Zgodnie z prawem konsumenckim przysługuje Ci 14 dni na odstąpienie od umowy — wystarczy się z nami skontaktować, a my zorganizujemy odbiór i zwrot środków.',
                'category' => 'returns',
            ],
            [
                'slug' => 'bulk',
                'question' => 'Czy obsługujecie zamówienia firmowe?',
                'answer' => 'Tak! Obsługujemy firmy i organizatorów eventów. Realizujemy zamówienia od kilku do kilkuset paczek z możliwością dostawy na różne adresy i personalizacji dedykacji. Skorzystaj z formularza na stronie „Dla firm".',
                'category' => 'b2b',
            ],
        ];

        foreach ($items as $i => $item) {
            FaqItem::updateOrCreate(
                ['slug' => $item['slug']],
                array_merge($item, [
                    'sort_order' => $i * 10,
                    'active' => true,
                ]),
            );
        }
    }

    private function seedTestimonials(): void
    {
        // Placeholder data — backoffice replaces with real reviews once collected.
        $items = [
            [
                'author_name' => 'Anna K.',
                'content' => 'Zamówiłam pakiet Premium dla mamy. Była zachwycona — każdy produkt idealnie dobrany do jej gustu. Polecam!',
                'rating' => 5,
                'source' => 'retail',
            ],
            [
                'author_name' => 'Tomasz W.',
                'content' => 'Szukałem prezentu dla żony i nie miałem pojęcia od czego zacząć. Ankieta załatwiła sprawę — dostałem gotową propozycję w 24h.',
                'rating' => 5,
                'source' => 'retail',
            ],
            [
                'author_name' => 'Katarzyna M.',
                'content' => 'Zamówiliśmy 30 pakietów VIP dla klientów firmy. Profesjonalna obsługa, piękne opakowania, wszyscy byli pod wrażeniem.',
                'rating' => 5,
                'source' => 'b2b',
            ],
        ];

        foreach ($items as $i => $item) {
            Testimonial::updateOrCreate(
                ['author_name' => $item['author_name']],
                array_merge($item, [
                    'sort_order' => $i * 10,
                    'active' => false,  // placeholder — keep hidden until real reviews collected
                ]),
            );
        }
    }
}
