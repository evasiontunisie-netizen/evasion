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
    <section x-cloak x-show="!isAuthenticated" class="min-h-screen overflow-hidden p-4 sm:p-6">
        <div class="mx-auto grid min-h-[calc(100vh-2rem)] max-w-7xl overflow-hidden rounded-[2rem] border border-black/10 bg-white shadow-soft dark:border-white/10 dark:bg-zinc-950 lg:grid-cols-[1.05fr_.95fr]">
            <div class="relative hidden bg-ink p-10 text-white dark:bg-black lg:block">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(255,77,25,.45),transparent_28%),radial-gradient(circle_at_80%_10%,rgba(255,255,255,.18),transparent_22%)]"></div>
                <div class="relative z-10 flex h-full flex-col justify-between">
                    <div class="flex items-center gap-3">
                        <div class="grid h-12 w-12 place-items-center rounded-2xl bg-white text-xl font-black text-ink">E</div>
                        <div>
                            <p class="text-sm text-white/60">Premium ERP Suite</p>
                            <h1 class="text-xl font-semibold">Evasion ERP</h1>
                        </div>
                    </div>
                    <div>
                        <p class="mb-4 inline-flex rounded-full bg-white/10 px-4 py-2 text-sm text-white/80">Multi-showrooms + WooCommerce + POS</p>
                        <h2 class="max-w-xl text-5xl font-semibold tracking-tight">Pilote toute ton entreprise depuis un cockpit moderne.</h2>
                        <p class="mt-5 max-w-lg text-white/65">Stocks, ventes, caisse, SAV, RH, livraison, marketing, comptabilité et analytics dans une interface rapide et responsive.</p>
                    </div>
                    <div class="grid grid-cols-3 gap-3 text-sm text-white/70">
                        <div class="rounded-3xl bg-white/10 p-4"><strong class="block text-2xl text-white">JWT</strong>Sécurité</div>
                        <div class="rounded-3xl bg-white/10 p-4"><strong class="block text-2xl text-white">PWA</strong>Mobile</div>
                        <div class="rounded-3xl bg-white/10 p-4"><strong class="block text-2xl text-white">Live</strong>Alertes</div>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-center p-5 sm:p-10">
                <div class="w-full max-w-md">
                    <div class="mb-8 flex items-center justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-[.32em] text-accent">Connexion sécurisée</p>
                            <h2 class="mt-2 text-3xl font-semibold tracking-tight" x-text="authMode === 'login' ? 'Bienvenue' : 'Créer Super Admin'"></h2>
                        </div>
                        <button @click="darkMode = !darkMode; persistTheme()" class="btn-secondary" x-text="darkMode ? 'Light' : 'Dark'"></button>
                    </div>

                    <div class="mb-5 grid grid-cols-2 rounded-full bg-zinc-100 p-1 dark:bg-white/10">
                        <button @click="authMode = 'login'" class="rounded-full px-4 py-3 text-sm font-semibold" :class="authMode === 'login' ? 'bg-white shadow dark:bg-black' : 'text-zinc-500'">Login</button>
                        <button @click="authMode = 'register'" class="rounded-full px-4 py-3 text-sm font-semibold" :class="authMode === 'register' ? 'bg-white shadow dark:bg-black' : 'text-zinc-500'">Premier admin</button>
                    </div>

                    <form @submit.prevent="authMode === 'login' ? login() : registerAdmin()" class="space-y-4">
                        <template x-if="authMode === 'register'">
                            <input x-model="authForm.name" class="input w-full" placeholder="Nom complet">
                        </template>
                        <input x-model="authForm.email" class="input w-full" type="email" placeholder="Email">
                        <input x-model="authForm.password" class="input w-full" type="password" placeholder="Mot de passe">
                        <template x-if="authMode === 'login'">
                            <input x-model="authForm.otp" class="input w-full" placeholder="Code 2FA si activé">
                        </template>
                        <button class="btn-primary w-full" :disabled="authLoading">
                            <span x-text="authLoading ? 'Veuillez patienter...' : (authMode === 'login' ? 'Se connecter' : 'Créer le compte admin')"></span>
                        </button>
                    </form>

                    <button @click="enterPreview()" class="btn-secondary mt-4 w-full">Voir l'interface en mode aperçu</button>
                    <p class="mt-5 text-sm leading-6 text-zinc-500">Pour une vraie connexion, importe `database/schema.sql`, puis `database/seed.sql`, crée le premier admin et connecte-toi. Le mode aperçu affiche seulement l'UI sans déverrouiller les API sécurisées.</p>
                </div>
            </div>
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
                    <button @click="setModule('<?= Security::e($item['key']) ?>')" class="nav-item" :class="module === '<?= Security::e($item['key']) ?>' && 'active'">
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
                        <button @click="darkMode = !darkMode; persistTheme()" class="btn-secondary" x-text="darkMode ? 'Light' : 'Dark'"></button>
                        <button @click="logout()" class="btn-secondary">Déconnexion</button>
                        <button @click="openCreate()" class="btn-primary">Créer</button>
                        <label x-show="module === 'products'" class="btn-secondary grid cursor-pointer place-items-center">
                            Import CSV
                            <input class="hidden" type="file" accept=".csv" @change="importCsv($event)">
                        </label>
                    </div>
                </div>
                <div class="mt-4 flex flex-col gap-3 md:flex-row">
                    <input x-model.debounce.300ms="query" @input="load()" class="input flex-1" placeholder="Recherche instantanée: produit, ticket, client, commande...">
                    <button @click="exportData('csv')" class="btn-secondary">CSV</button>
                    <button @click="exportData('pdf')" class="btn-secondary">PDF</button>
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
