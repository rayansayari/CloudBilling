<?php

namespace App\Service;

use App\Repository\AuditLogRepository;
use App\Repository\CompanyRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ChatbotService
{
    public function __construct(
        private readonly CompanyRepository  $companyRepository,
        private readonly ProjectRepository  $projectRepository,
        private readonly InvoiceRepository  $invoiceRepository,
        private readonly UserRepository     $userRepository,
        private readonly AuditLogRepository $auditLogRepository,
        #[Autowire(env: 'GEMINI_API_KEY')]
        private readonly string             $geminiApiKey,
    ) {}

    /**
     * Interroge Gemini avec le contexte de la base de données.
     */
    public function chat(string $userMessage, array $history = []): string
    {
        $context = $this->buildDatabaseContext();
        $systemPrompt = $this->buildSystemPrompt($context);

        // Construire les messages pour Gemini
        $contents = [];

        // Historique de conversation
        foreach ($history as $msg) {
            $contents[] = [
                'role'  => $msg['role'],
                'parts' => [['text' => $msg['text']]],
            ];
        }

        // Message actuel de l'utilisateur
        $contents[] = [
            'role'  => 'user',
            'parts' => [['text' => $userMessage]],
        ];

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents'           => $contents,
            'generationConfig'   => [
                'temperature'     => 0.7,
                'maxOutputTokens' => 1024,
            ],
        ];

        $url     = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent?key=' . $this->geminiApiKey;
        $json    = json_encode($payload);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return '⚠️ Erreur de connexion à l\'API Gemini : ' . $error;
        }

        $data = json_decode($response, true);

        if (!empty($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        }

        if (!empty($data['error']['message'])) {
            return '⚠️ Erreur API : ' . $data['error']['message'];
        }

        return '⚠️ Réponse inattendue de l\'API.';
    }

    /**
     * Construit le contexte à partir de la base de données.
     */
    private function buildDatabaseContext(): array
    {
        $year = (int) date('Y');

        // Sociétés
        $companies = $this->companyRepository->findAll();
        $companyList = [];
        foreach ($companies as $c) {
            $companyList[] = sprintf(
                '- %s (ID:%d, %d projets)',
                $c->getName(),
                $c->getId(),
                count($c->getProjects())
            );
        }

        // Projets
        $projects = $this->projectRepository->findAll();
        $projectList = [];
        foreach ($projects as $p) {
            $projectList[] = sprintf(
                '- SO:%s "%s" → Société:%s, Statut:%s, Factures:%d',
                $p->getSoNumber(),
                $p->getName(),
                $p->getCompany()->getName(),
                $p->getStatus() ?? 'N/A',
                count($p->getInvoices())
            );
        }

        // Factures récentes (30 dernières)
        $allInvoices   = $this->invoiceRepository->findBy([], ['year' => 'DESC', 'month' => 'DESC'], 30);
        $invoiceList   = [];
        $totalValidated = 0;
        $totalDraft     = 0;
        foreach ($allInvoices as $inv) {
            $status = $inv->isValidated() ? 'Validée' : 'Brouillon';
            if ($inv->isValidated()) {
                $totalValidated++;
            } else {
                $totalDraft++;
            }
            $invoiceList[] = sprintf(
                '- %s (%s/%s) → %s, %.2f TND, %s',
                $inv->getReference(),
                $inv->getMonth(),
                $inv->getYear(),
                $inv->getProject()->getSoNumber(),
                $inv->getTotalTTC(),
                $status
            );
        }

        // CA annuel
        $totalBilled = $this->invoiceRepository->getTotalBilledByYear($year);

        // Utilisateurs
        $users     = $this->userRepository->findAll();
        $userList  = [];
        foreach ($users as $u) {
            $role = in_array('ROLE_ADMIN', $u->getRoles()) ? 'Admin' : 'Consultant';
            $userList[] = sprintf('- %s (%s) → %s', $u->getFullName(), $u->getEmail(), $role);
        }

        // Dernières actions audit
        $logs    = $this->auditLogRepository->findBy([], ['createdAt' => 'DESC'], 10);
        $logList = [];
        foreach ($logs as $log) {
            $logList[] = sprintf(
                '- [%s] %s par %s : %s',
                $log->getCreatedAt()->format('d/m/Y H:i'),
                $log->getAction(),
                $log->getUser()?->getFullName() ?? 'Système',
                $log->getDetails()
            );
        }

        return [
            'companies'      => $companyList,
            'projects'       => $projectList,
            'invoices'       => $invoiceList,
            'totalBilled'    => $totalBilled,
            'totalValidated' => $totalValidated,
            'totalDraft'     => $totalDraft,
            'users'          => $userList,
            'auditLogs'      => $logList,
            'year'           => $year,
        ];
    }

    /**
     * Construit le prompt système avec les données.
     */
    private function buildSystemPrompt(array $ctx): string
    {
        $companies = implode("\n", $ctx['companies']) ?: 'Aucune';
        $projects  = implode("\n", $ctx['projects'])  ?: 'Aucun';
        $invoices  = implode("\n", $ctx['invoices'])  ?: 'Aucune';
        $users     = implode("\n", $ctx['users'])     ?: 'Aucun';
        $logs      = implode("\n", $ctx['auditLogs']) ?: 'Aucun';

        $year = $ctx['year'];
        $totalBilled = $ctx['totalBilled'];
        $totalValidated = $ctx['totalValidated'];
        $totalDraft = $ctx['totalDraft'];
        
        $countCompanies = count($ctx['companies']);
        $countProjects = count($ctx['projects']);

        return <<<PROMPT
Tu es un assistant IA intelligent intégré dans CloudBill, un système de gestion de facturation cloud pour la société NetSolutions (NS).
Tu as accès en temps réel aux données de la base de données ci-dessous.
Tu réponds uniquement en français, de manière claire, concise et professionnelle.
Tu aides l'administrateur à analyser ses données, comprendre les tendances et répondre à ses questions.

=== DONNÉES EN TEMPS RÉEL ===

📅 Année en cours : {$year}
💰 CA total validé {$year} : {$totalBilled} TND
📄 Factures validées : {$totalValidated} | Brouillons : {$totalDraft}

🏢 SOCIÉTÉS CLIENTES ({$countCompanies}) :
$companies

📁 PROJETS (SO) ({$countProjects}) :
$projects

🧾 DERNIÈRES FACTURES (30 plus récentes) :
$invoices

👥 UTILISATEURS DU SYSTÈME :
$users

📋 DERNIÈRES ACTIONS AUDIT (10 plus récentes) :
$logs

=== FIN DES DONNÉES ===

Instructions :
- Réponds toujours en français
- Sois précis et utilise les données ci-dessus pour répondre
- Si une question dépasse tes données disponibles, dis-le clairement
- Formate les nombres avec 2 décimales et l'unité TND pour les montants
- Utilise des emojis pour rendre les réponses plus lisibles
PROMPT;
    }
}
