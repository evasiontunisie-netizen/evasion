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
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Evasion ERP</p>
                        <h1 class="text-2xl font-semibold tracking-tight" x-text="authMode === 'login' ? 'Connexion' : 'Premier admin'"></h1>
                    </div>
                </div>
                <button @click="darkMode = !darkMode; persistTheme()" class="btn-secondary px-4" x-text="darkMode ? 'Light' : 'Dark'"></button>
            </div>

            <div class="mb-5 grid grid-cols-2 rounded-full bg-zinc-100 p-1 dark:bg-white/10">
                <button @click="authMode = 'login'" class="rounded-full px-4 py-3 text-sm font-semibold" :class="authMode === 'login' ? 'bg-white shadow dark:bg-black' : 'text-zinc-500'">Login</button>
                <button @click="authMode = 'register'" class="rounded-full px-4 py-3 text-sm font-semibold" :class="authMode === 'register' ? 'bg-white shadow dark:bg-black' : 'text-zinc-500'">Admin</button>
            </div>

            <form @submit.prevent="authMode === 'login' ? login() : registerAdmin()" class="space-y-4">
                <template x-if="authMode === 'register'">
                    <input x-model="authForm.name" class="input w-full" placeholder="Nom complet">
                </template>
                <input x-model="authForm.email" class="input w-full" type="email" placeholder="Email">
                <input x-model="authForm.password" class="input w-full" type="password" placeholder="Mot de passe">
                <template x-if="authMode === 'login'">
                    <input x-model="authForm.otp" class="input w-full" placeholder="Code 2FA">
                </template>
                <button class="btn-primary w-full" :disabled="authLoading">
                    <span x-text="authLoading ? 'Patientez...' : (authMode === 'login' ? 'Se connecter' : 'Créer le compte')"></span>
                </button>
            </form>

            <button @click="enterPreview()" class="mt-4 w-full text-sm font-semibold text-accent">Mode aperçu</button>
        </div>
    </section>

    <div x-cloak x-show="isAuthenticated" class="min-h-screen lg:grid lg:grid-cols-[280px_1fr]">
        <aside class="fixed inset-x-0 bottom-0 z-30 border-t border-black/10 bg-white/95 backdrop-blur lg:static lg:h-screen lg:border-r lg:border-t-0 dark:border-white/10 dark:bg-zinc-950/95">
            <div class="hidden px-6 py-6 lg:block">
                <div class="flex items-center gap-3">
                    <div class="grid h-11 w-11 place-items-center rounded-2xl bg-black text-white dark:bg-white dark:text-black">E</div>
                    <div>
                        <p class="text-sm text-zinc-500">Premium Suite</p>
                        <h1 class="font-semibold tracking-tight">Evasion ERP</h1>
                    </div>
                </div>
            </div>
            <nav class="flex gap-1 overflow-x-auto px-3 py-2 lg:block lg:space-y-1 lg:px-4">
                <?php foreach ($nav as $item): ?>
                    <button x-show="can('<?= Security::e($item['permission']) ?>')" @click="setModule('<?= Security::e($item['key']) ?>')" class="nav-item" :class="module === '<?= Security::e($item['key']) ?>' && 'active'">
                        <span><?= Security::e($item['label']) ?></span>
                    </button>
                <?php endforeach; ?>
            </nav>
        </aside>

        <main class="pb-24 lg:pb-0">
            <header class="sticky top-0 z-20 border-b border-black/10 bg-[#f6f6f3]/85 px-4 py-4 backdrop-blur-xl dark:border-white/10 dark:bg-black/80 sm:px-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[.35em] text-accent">Multi-showroom + WooCommerce</p>
                        <h2 class="mt-1 text-2xl font-semibold tracking-tight" x-text="title"></h2>
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
                    <div class="grid gap-4 xl:grid-cols-[1.7fr_1fr]">
                        <article class="card">
                            <h3 class="section-title mb-4">Caisse POS rapide</h3>
                            <input x-model="posSearch" class="input mb-4" placeholder="Scanner barcode / QR ou rechercher produit">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <template x-for="product in demoProducts" :key="product.sku">
                                    <button @click="addToCart(product)" class="rounded-3xl border border-black/10 p-4 text-left transition hover:-translate-y-1 hover:shadow-soft dark:border-white/10">
                                        <p class="font-semibold" x-text="product.name"></p>
                                        <p class="text-sm text-zinc-500" x-text="product.sku"></p>
                                        <p class="mt-3 text-lg font-bold" x-text="money(product.price)"></p>
                                    </button>
                                </template>
                            </div>
                        </article>
                        <article class="card">
                            <h3 class="section-title mb-4">Panier</h3>
                            <template x-for="item in cart" :key="item.sku">
                                <div class="flex items-center justify-between border-b border-black/5 py-3 dark:border-white/10">
                                    <div>
                                        <p class="font-medium" x-text="item.name"></p>
                                        <p class="text-sm text-zinc-500" x-text="'x' + item.quantity"></p>
                                    </div>
                                    <strong x-text="money(item.price * item.quantity)"></strong>
                                </div>
                            </template>
                            <div class="mt-5 flex items-center justify-between text-xl font-bold">
                                <span>Total</span><span x-text="money(cartTotal)"></span>
                            </div>
                            <button @click="checkout()" class="btn-primary mt-5 w-full">Encaisser</button>
                        </article>
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
