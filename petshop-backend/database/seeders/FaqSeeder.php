<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'Quels sont les délais et frais de livraison à Marrakech et au Maroc ?',
                'answer' => 'Nous livrons en moins de 24h à Marrakech (coursier express) et en 24h à 48h dans toutes les autres villes du Maroc (Casablanca, Rabat, Tanger, Fès, Agadir...). La livraison est 100% offerte dès 300 DH d\'achats (sinon seulement 25 DH pour les commandes inférieures).',
                'is_active' => true,
                'order' => 1,
            ],
            [
                'question' => 'Comment fonctionne le paiement à la livraison (Cash on Delivery) ?',
                'answer' => 'Vous payez en toute sécurité en espèces directement auprès du livreur au moment de la réception de votre colis. Aucune carte bancaire n\'est exigée lors du passage de votre commande en ligne.',
                'is_active' => true,
                'order' => 2,
            ],
            [
                'question' => 'Proposez-vous des croquettes au kilo (en vrac) ou seulement des sacs complets ?',
                'answer' => 'Nous proposons les deux formules ! Vous pouvez commander des sacs fermés d\'origine (3kg, 10kg, 15kg...) ou opter pour des portions pesées au kilo selon vos besoins et votre budget.',
                'is_active' => true,
                'order' => 3,
            ],
            [
                'question' => 'Est-il possible de retirer ma commande directement en magasin (Click & Collect) ?',
                'answer' => 'Oui, c\'est totalement gratuit ! Sélectionnez l\'option "Retrait en Magasin (Click & Collect)" lors de la commande et venez récupérer vos articles dans la boutique de votre choix dès que votre colis est préparé.',
                'is_active' => true,
                'order' => 4,
            ],
            [
                'question' => 'Puis-je obtenir un conseil personnalisé pour mon animal via WhatsApp ?',
                'answer' => 'Absolument ! Notre équipe d\'experts et passionnés d\'animaux est à votre disposition 7j/7 sur WhatsApp pour vous orienter vers l\'alimentation, les soins et les accessoires les plus adaptés à la race, l\'âge et la sensibilité de votre compagnon.',
                'is_active' => true,
                'order' => 5,
            ],
            [
                'question' => 'Que faire si le produit reçu est endommagé ou non conforme ?',
                'answer' => 'Votre satisfaction est notre priorité absolue. En cas d\'anomalie ou de produit défectueux à la réception, contactez notre service client sous 48h (via WhatsApp ou téléphone) avec une photo du produit pour un échange immédiat ou un remboursement complet.',
                'is_active' => true,
                'order' => 6,
            ],
        ];

        foreach ($faqs as $faqData) {
            Faq::updateOrCreate(
                ['question' => $faqData['question']],
                $faqData
            );
        }
    }
}