<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Security;
use App\Support\ModuleRegistry;

final class ViewController
{
    public function app(): void
    {
        $nav = ModuleRegistry::navigation();
        ob_start();
        ?>
<!doctype html>
<html lang="fr" x-data="erpApp()" x-init="init()" :class="{ 'dark': darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#111111">
    <meta name="csrf-token" content="<?= Security::e(Security::csrfToken()) ?>">
    <link rel="manifest" href="/manifest.json">
    <title>Evasion ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { accent: '#ff4d19', ink: '#111111' }, boxShadow: { soft: '0 20px 60px rgba(15,23,42,.10)' } } } }</script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="bg-[#f6f6f3] text-ink antialiased transition dark:bg-black dark:text-white">
    <section x-cloak x-show="!isAuthenticated" class="login-shell min-h-screen p-4">
        <div class="login-card mx-auto w-full max-w-md">
            <div class="mb-8 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="login-logo">E</div>
                    <div>
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Evasion</p>
                        <h1 class="text-2xl font-semibold tracking-tight">Connexion</h1>
                    </div>
                </div>
                <button @click="darkMode = !darkMode; persistTheme()" class="btn-secondary px-4" x-text="darkMode ? 'Light' : 'Dark'"></button>
            </div>

            <div class="mb-5 grid gap-2 sm:grid-cols-2">
                <template x-for="account in demoAccounts" :key="account.email">
                    <button type="button" @click="fillDemoAccount(account)" class="demo-account" :class="authForm.email === account.email && 'active'">
                        <span x-text="account.label"></span>
                        <small x-text="account.role"></small>
                    </button>
                </template>
            </div>

            <form @submit.prevent="login()" class="space-y-4">
                <input x-model="authForm.email" class="input w-full" type="email" placeholder="Email">
                <input x-model="authForm.password" class="input w-full" type="password" placeholder="Mot de passe">
                <input x-model="authForm.otp" class="input w-full" placeholder="Code 2FA">
                <button class="btn-primary w-full" :disabled="authLoading">
                    <span x-text="authLoading ? 'Patientez...' : 'Se connecter'"></span>
                </button>
            </form>

            <button @click="enterPreview()" class="mt-4 w-full text-sm font-semibold text-accent">Mode aperçu</button>
        </div>
    </section>

    <div x-cloak x-show="isAuthenticated" class="app-shell min-h-screen">
        <button x-show="menuOpen" x-transition.opacity @click="menuOpen = false; persistMenu()" class="menu-backdrop lg:hidden" aria-label="Fermer le menu"></button>

        <aside x-show="menuOpen" x-transition class="menu-panel">
            <div class="flex items-center justify-between px-5 py-5">
                <div class="flex items-center gap-3">
                    <div class="grid h-11 w-11 place-items-center rounded-2xl bg-black text-white dark:bg-white dark:text-black">E</div>
                    <div>
                        <p class="text-sm text-zinc-500">ERP</p>
                        <h1 class="font-semibold tracking-tight">Evasion ERP</h1>
                    </div>
                </div>
                <button @click="menuOpen = false; persistMenu()" class="btn-secondary px-4">Fermer</button>
            </div>
            <nav class="space-y-1 px-4 pb-5">
                <?php foreach ($nav as $item): ?>
                    <button x-show="can('<?= Security::e($item['permission']) ?>')" @click="setModule('<?= Security::e($item['key']) ?>')" class="nav-item" :class="module === '<?= Security::e($item['key']) ?>' && 'active'">
                        <span><?= Security::e($item['label']) ?></span>
                    </button>
                <?php endforeach; ?>
            </nav>
        </aside>

        <main class="pb-24 transition-all duration-300 lg:pb-0" :class="menuOpen ? 'lg:pl-[300px]' : ''">
            <header class="sticky top-0 z-20 border-b border-black/10 bg-[#f6f6f3]/85 px-4 py-4 backdrop-blur-xl dark:border-white/10 dark:bg-black/80 sm:px-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <button @click="menuOpen = !menuOpen; persistMenu()" class="btn-secondary px-4" x-text="menuOpen ? 'Masquer menu' : 'Menu'"></button>
                        <div>
                            <p class="text-xs uppercase tracking-[.35em] text-accent">Evasion ERP</p>
                            <h2 class="mt-1 text-2xl font-semibold tracking-tight" x-text="title"></h2>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <select x-model="locale" class="input w-24">
                            <option value="fr">FR</option>
                            <option value="ar">AR</option>
                            <option value="en">EN</option>
                        </select>
                        <span class="hidden rounded-full px-3 py-2 text-xs font-semibold sm:inline-flex" :class="wsConnected ? 'bg-emerald-100 text-emerald-700' : 'bg-zinc-100 text-zinc-500'" x-text="wsConnected ? 'Live' : 'Offline'"></span>
                        <button @click="openAi()" class="btn-secondary">IA</button>
                        <button @click="open2fa()" class="btn-secondary">2FA</button>
                        <button @click="darkMode = !darkMode; persistTheme()" class="btn-secondary" x-text="darkMode ? 'Light' : 'Dark'"></button>
                        <button @click="logout()" class="btn-secondary">Déconnexion</button>
                        <button x-show="canCreateCurrent()" @click="openCreate()" class="btn-primary">Créer</button>
                        <button x-show="module === 'invoices' && can('accounting.manage')" @click="openInvoicePdf()" class="btn-primary">PDF facture</button>
                        <label x-show="module === 'products' && can('products.manage')" class="btn-secondary grid cursor-pointer place-items-center">
                            Import CSV
                            <input class="hidden" type="file" accept=".csv" @change="importCsv($event)">
                        </label>
                    </div>
                </div>
                <div class="mt-4 flex flex-col gap-3 md:flex-row">
                    <input x-model.debounce.300ms="query" @input="load()" class="input flex-1" placeholder="Recherche instantanée: produit, ticket, client, commande...">
                    <button x-show="canReadCurrent()" @click="exportData('csv')" class="btn-secondary">CSV</button>
                    <button x-show="canReadCurrent()" @click="exportData('pdf')" class="btn-secondary">PDF</button>
                </div>
            </header>

            <section class="space-y-6 p-4 sm:p-6">
                <template x-if="module === 'dashboard'">
                    <div class="space-y-6">
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <template x-for="card in kpiCards" :key="card.label">
                                <article class="card">
                                    <p class="text-sm text-zinc-500" x-text="card.label"></p>
                                    <div class="mt-3 flex items-end justify-between">
                                        <strong class="text-3xl tracking-tight" x-text="card.value"></strong>
                                        <span class="rounded-full bg-orange-100 px-3 py-1 text-xs font-medium text-accent dark:bg-orange-500/10">Live</span>
                                    </div>
                                </article>
                            </template>
                        </div>
                        <article class="card hero-card">
                            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                                <div>
                                    <p class="text-xs uppercase tracking-[.3em] text-accent">Assistant IA</p>
                                    <h3 class="mt-2 text-2xl font-black tracking-tight" x-text="ai.summary || 'Analyse opérationnelle prête'"></h3>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-zinc-500">Score ERP</p>
                                    <strong class="text-5xl" x-text="(ai.score || 0) + '%'"></strong>
                                </div>
                            </div>
                            <div class="mt-5 grid gap-3 md:grid-cols-3">
                                <template x-for="action in ai.actions" :key="action">
                                    <div class="rounded-2xl bg-white/70 p-4 text-sm font-medium shadow-sm dark:bg-black/30" x-text="action"></div>
                                </template>
                            </div>
                        </article>
                        <div class="grid gap-4 xl:grid-cols-[2fr_1fr]">
                            <article class="card">
                                <div class="mb-4 flex items-center justify-between">
                                    <h3 class="section-title">Ventes 30 jours</h3>
                                    <span class="text-sm text-zinc-500">CA quotidien</span>
                                </div>
                                <canvas id="salesChart" height="110"></canvas>
                            </article>
                            <article class="card">
                                <h3 class="section-title mb-4">Canaux</h3>
                                <canvas id="channelChart" height="190"></canvas>
                            </article>
                        </div>
                        <article class="card">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="section-title">Produits par catégorie</h3>
                                <span class="text-sm text-zinc-500">Depuis WooCommerce / ERP</span>
                            </div>
                            <canvas id="productChart" height="90"></canvas>
                        </article>
                    </div>
                </template>

                <template x-if="module === 'pos'">
                    <div class="grid gap-4 2xl:grid-cols-[1.45fr_.95fr]">
                        <article class="card">
                            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 class="section-title">Caisse</h3>
                                    <p class="text-sm text-zinc-500">Vente rapide.</p>
                                </div>
                                <span class="rounded-full bg-orange-100 px-3 py-1 text-xs font-bold text-accent dark:bg-orange-500/10" x-text="posProducts.length + ' produits'"></span>
                            </div>
                            <div class="mb-4 grid gap-3 md:grid-cols-[1fr_120px]">
                                <input x-model.debounce.250ms="posSearch" @input="loadPosCatalog()" class="input" placeholder="Scanner ou rechercher">
                                <input x-model.number="posWarehouseId" @change="loadPosCatalog()" class="input" type="number" min="1" placeholder="Stock">
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                <template x-for="product in posProducts" :key="product.sku">
                                    <button @click="addToCart(product)" class="pos-product-card">
                                        <img :src="product.image_url || '/assets/icon-192.svg'" :alt="product.name" loading="lazy">
                                        <div class="p-4">
                                            <p class="line-clamp-2 font-bold" x-text="product.name"></p>
                                            <p class="mt-1 text-xs text-zinc-500" x-text="product.sku"></p>
                                            <div class="mt-3 flex items-center justify-between">
                                                <strong class="text-accent" x-text="money(product.price)"></strong>
                                                <span class="rounded-full bg-zinc-100 px-2 py-1 text-xs dark:bg-white/10" x-text="'Stock ' + product.stock"></span>
                                            </div>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </article>
                        <div class="space-y-4">
                            <article class="card">
                                <div class="mb-4 flex items-center justify-between">
                                    <h3 class="section-title">Panier</h3>
                                    <input x-model="posCustomerId" class="input w-28" placeholder="Client ID">
                                </div>
                                <template x-for="item in cart" :key="item.sku">
                                    <button @click="selectCartItem(item)" class="cart-line" :class="selectedCartSku === item.sku && 'active'">
                                        <div>
                                            <p class="font-medium" x-text="item.name"></p>
                                            <p class="text-sm text-zinc-500" x-text="'x' + item.quantity + ' - ' + item.sku"></p>
                                        </div>
                                        <div class="text-right">
                                            <strong x-text="money(item.price * item.quantity)"></strong>
                                            <span @click.stop="removeCartItem(item)" class="mt-1 block text-xs font-bold text-red-500">Retirer</span>
                                        </div>
                                    </button>
                                </template>
                                <div class="mt-4 space-y-2 rounded-3xl bg-zinc-50 p-4 text-sm dark:bg-white/5">
                                    <div class="flex justify-between"><span>Sous-total</span><strong x-text="money(cartSubtotal)"></strong></div>
                                    <div class="flex justify-between"><span>Remise</span><strong x-text="money(posDiscount || 0)"></strong></div>
                                    <div class="flex justify-between"><span>TVA</span><strong x-text="money(posTaxTotal)"></strong></div>
                                    <div class="flex justify-between text-xl font-black"><span>Total</span><strong x-text="money(cartTotal)"></strong></div>
                                </div>
                            </article>

                            <article class="card">
                                <h3 class="section-title mb-4">Clavier</h3>
                                <div class="mb-3 rounded-2xl bg-black px-4 py-3 text-right text-2xl font-black text-white" x-text="keypadValue || '0'"></div>
                                <div class="grid grid-cols-3 gap-2">
                                    <template x-for="key in ['1','2','3','4','5','6','7','8','9','0','00','.']" :key="key">
                                        <button @click="pressKeypad(key)" class="keypad-btn" x-text="key"></button>
                                    </template>
                                    <button @click="pressKeypad('back')" class="keypad-btn">⌫</button>
                                    <button @click="pressKeypad('clear')" class="keypad-btn">C</button>
                                    <button @click="applyKeypadQuantity()" class="keypad-btn primary">Qté</button>
                                </div>
                                <button @click="applyKeypadDiscount()" class="btn-secondary mt-3 w-full">Appliquer remise</button>
                            </article>

                            <article class="card">
                                <div class="mb-4 flex items-center justify-between">
                                    <h3 class="section-title">Paiements</h3>
                                    <button @click="addPaymentRow()" class="btn-secondary px-4">+</button>
                                </div>
                                <div class="space-y-3">
                                    <template x-for="(payment, index) in posPayments" :key="index">
                                        <div class="grid gap-2 rounded-2xl border border-black/10 p-3 dark:border-white/10">
                                            <select x-model="payment.method" class="input w-full">
                                                <template x-for="method in paymentMethods" :key="method[0]">
                                                    <option :value="method[0]" x-text="method[1]"></option>
                                                </template>
                                            </select>
                                            <div class="grid grid-cols-[1fr_auto] gap-2">
                                                <input x-model.number="payment.amount" class="input" type="number" step="0.001" placeholder="Montant">
                                                <button @click="setPaymentToRemaining(index)" class="btn-secondary px-4">Reste</button>
                                            </div>
                                            <input x-model="payment.reference" class="input w-full" placeholder="Référence">
                                            <button x-show="posPayments.length > 1" @click="removePaymentRow(index)" class="text-sm font-bold text-red-500">Supprimer</button>
                                        </div>
                                    </template>
                                </div>
                                <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                                    <div class="rounded-2xl bg-zinc-100 p-3 dark:bg-white/10"><span class="block text-zinc-500">Payé</span><strong x-text="money(posPaidTotal)"></strong></div>
                                    <div class="rounded-2xl bg-zinc-100 p-3 dark:bg-white/10"><span class="block text-zinc-500">Reste</span><strong x-text="money(posRemaining)"></strong></div>
                                    <div class="rounded-2xl bg-zinc-100 p-3 dark:bg-white/10"><span class="block text-zinc-500">Rendu</span><strong x-text="money(posChangeDue)"></strong></div>
                                </div>
                                <button @click="checkout()" class="btn-primary mt-5 w-full">Encaisser</button>
                            </article>

                            <article x-show="lastPosSale" class="card">
                                <h3 class="section-title mb-4">Documents</h3>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <button @click="downloadPdf(lastPosSale.ticket_pdf_url, 'ticket-pos.pdf')" class="btn-secondary">Ticket PDF</button>
                                    <button @click="downloadPdf(lastPosSale.invoice_pdf_url, 'facture-pos.pdf')" class="btn-secondary">Facture PDF</button>
                                </div>
                                <p class="mt-3 text-sm text-zinc-500" x-text="lastPosSale.order ? lastPosSale.order.order_number : ''"></p>
                            </article>
                        </div>
                    </div>
                </template>

                <template x-if="module === 'products'">
                    <article class="card">
                        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="section-title">Catalogue produits avec photos</h3>
                                <p class="text-sm text-zinc-500">Compatible CSV WooCommerce: Nom, UGS, prix, stock, catégories, images.</p>
                            </div>
                            <span class="rounded-full border border-black/10 px-3 py-1 text-sm text-zinc-500 dark:border-white/10" x-text="rows.length + ' produits'"></span>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <template x-for="product in rows" :key="product.id">
                                <article class="overflow-hidden rounded-[1.5rem] border border-black/10 bg-white dark:border-white/10 dark:bg-white/5">
                                    <img :src="product.image_url || '/assets/icon-192.svg'" :alt="product.name" class="h-44 w-full object-cover" loading="lazy">
                                    <div class="space-y-2 p-4">
                                        <p class="line-clamp-2 font-bold" x-text="product.name"></p>
                                        <p class="text-xs text-zinc-500" x-text="product.sku"></p>
                                        <div class="flex items-center justify-between">
                                            <strong class="text-accent" x-text="money(product.promo_price || product.sale_price || 0)"></strong>
                                            <span class="rounded-full bg-zinc-100 px-2 py-1 text-xs dark:bg-white/10" x-text="product.status"></span>
                                        </div>
                                    </div>
                                </article>
                            </template>
                        </div>
                    </article>
                </template>

                <template x-if="module === 'users'">
                    <article class="card">
                        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="section-title">Utilisateurs avec photos</h3>
                                <p class="text-sm text-zinc-500">Comptes, rôles, avatars et statut.</p>
                            </div>
                            <span class="rounded-full border border-black/10 px-3 py-1 text-sm text-zinc-500 dark:border-white/10" x-text="rows.length + ' users'"></span>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <template x-for="user in rows" :key="user.id">
                                <article class="rounded-[1.5rem] border border-black/10 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                                    <img :src="user.avatar_path || '/assets/icon-192.svg'" :alt="user.name" class="mx-auto h-24 w-24 rounded-full object-cover ring-4 ring-orange-100 dark:ring-orange-500/20" loading="lazy">
                                    <div class="mt-4 text-center">
                                        <p class="font-bold" x-text="user.name"></p>
                                        <p class="text-sm text-zinc-500" x-text="user.email"></p>
                                        <span class="mt-3 inline-flex rounded-full bg-zinc-100 px-3 py-1 text-xs dark:bg-white/10" x-text="user.status"></span>
                                    </div>
                                </article>
                            </template>
                        </div>
                    </article>
                </template>

                <template x-if="module === 'invoices'">
                    <div class="grid gap-4 md:grid-cols-4">
                        <template x-for="item in accountingCards" :key="item.label">
                            <article class="card">
                                <p class="text-sm text-zinc-500" x-text="item.label"></p>
                                <strong class="mt-2 block text-2xl" x-text="item.value"></strong>
                            </article>
                        </template>
                    </div>
                </template>

                <template x-if="module !== 'dashboard' && module !== 'pos' && module !== 'products' && module !== 'users'">
                    <article class="card">
                        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="section-title" x-text="title"></h3>
                                <p class="text-sm text-zinc-500">CRUD sécurisé, export, recherche, filtres et activité temps réel.</p>
                            </div>
                            <span class="rounded-full border border-black/10 px-3 py-1 text-sm text-zinc-500 dark:border-white/10" x-text="rows.length + ' lignes'"></span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="modern-table">
                                <thead>
                                    <tr><template x-for="column in columns" :key="column"><th x-text="column"></th></template></tr>
                                </thead>
                                <tbody>
                                    <template x-for="row in rows" :key="row.id">
                                        <tr>
                                            <template x-for="column in columns" :key="column">
                                                <td x-text="row[column] ?? '-'"></td>
                                            </template>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </template>
            </section>
        </main>
    </div>

    <div x-cloak x-show="isAuthenticated" class="fixed bottom-5 right-5 z-40">
        <button @click="openAi()" class="grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br from-black to-accent font-black text-white shadow-soft">IA</button>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="/assets/app.js"></script>
</body>
</html>
        <?php
        Response::html((string) ob_get_clean());
    }
}
