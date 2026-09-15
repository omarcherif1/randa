import AdminNav from '@/Components/admin/AdminNav';
import PanelSection from '@/Components/PanelSection';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import DangerButton from '@/Components/DangerButton';
import Modal from '@/Components/Modal';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import Checkbox from '@/Components/Checkbox';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Fragment, useState } from 'react';

function AccessEditor({ user, societes, modeles, pagesCatalogue, onDone }) {
    const assignables = pagesCatalogue.filter((p) => !p.adminOnly);
    const form = useForm({
        is_admin: user.is_admin,
        template: '',
        societes: societes.filter((s) => user.societes.includes(s.code)).map((s) => s.id),
        pages: [...user.pages].filter((k) => assignables.some((p) => p.key === k)),
    });

    const toggle = (field, value) => {
        const set = new Set(form.data[field]);
        set.has(value) ? set.delete(value) : set.add(value);
        form.setData(field, [...set]);
    };

    const submit = (e) => {
        e.preventDefault();
        form.put(route('admin.users.access', user.id), { preserveScroll: true, onSuccess: onDone });
    };

    return (
        <form onSubmit={submit} className="mt-2 space-y-3 rounded-md bg-gray-50 dark:bg-gray-800/60 p-4 text-sm">
            <label className="flex items-center gap-2">
                <Checkbox
                    checked={form.data.is_admin}
                    onChange={(e) => form.setData('is_admin', e.target.checked)}
                />
                Administrateur (Administration + Paramètres, toutes les pages)
            </label>

            <div>
                <InputLabel value="Appliquer un modèle (pré-remplit les pages)" />
                <select
                    className="mt-1 rounded-md border-gray-300 dark:border-gray-700 text-sm"
                    value={form.data.template}
                    onChange={(e) => form.setData('template', e.target.value)}
                >
                    <option value="">—</option>
                    {modeles.map((m) => (
                        <option key={m.code} value={m.code}>
                            {m.nom}
                        </option>
                    ))}
                </select>
            </div>

            <div>
                <InputLabel value="Sociétés (filtre réellement les données visibles)" />
                <div className="mt-1 flex flex-wrap gap-3">
                    {societes.map((s) => (
                        <label key={s.id} className="flex items-center gap-1">
                            <Checkbox
                                checked={form.data.societes.includes(s.id)}
                                onChange={() => toggle('societes', s.id)}
                            />
                            {s.code}
                        </label>
                    ))}
                </div>
                <InputError message={form.errors.societes} />
            </div>

            {!form.data.is_admin && !form.data.template && (
                <div>
                    <InputLabel value="Pages accordées (une par une)" />
                    <div className="mt-1 grid grid-cols-2 gap-2 sm:grid-cols-3">
                        {assignables.map((p) => (
                            <label key={p.key} className="flex items-center gap-1">
                                <Checkbox
                                    checked={form.data.pages.includes(p.key)}
                                    onChange={() => toggle('pages', p.key)}
                                />
                                {p.label}
                            </label>
                        ))}
                    </div>
                </div>
            )}

            <div className="flex gap-2">
                <PrimaryButton disabled={form.processing}>Enregistrer</PrimaryButton>
                <SecondaryButton type="button" onClick={onDone}>
                    Annuler
                </SecondaryButton>
            </div>
        </form>
    );
}

function CreateUser({ societes, pagesCatalogue }) {
    const assignables = pagesCatalogue.filter((p) => !p.adminOnly);
    const form = useForm({
        name: '',
        email: '',
        is_admin: false,
        societes: societes.map((s) => s.id),
        pages: [],
    });
    const [open, setOpen] = useState(false);

    const toggle = (field, value) => {
        const set = new Set(form.data[field]);
        set.has(value) ? set.delete(value) : set.add(value);
        form.setData(field, [...set]);
    };

    const fermer = () => {
        setOpen(false);
        form.reset();
        form.clearErrors();
    };

    const submit = (e) => {
        e.preventDefault();
        form.post(route('admin.users.store'), {
            preserveScroll: true,
            onSuccess: fermer,
        });
    };

    return (
        <>
            <PrimaryButton onClick={() => setOpen(true)}>Nouveau compte</PrimaryButton>

            <Modal show={open} onClose={fermer}>
                <form onSubmit={submit} className="space-y-4 p-6 text-sm">
                    <h2 className="font-semibold text-gray-800 dark:text-gray-100">Nouveau compte</h2>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel htmlFor="name" value="Nom" />
                            <TextInput
                                id="name"
                                className="mt-1 block w-full"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                isFocused
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div>
                            <InputLabel htmlFor="email" value="E-mail" />
                            <TextInput
                                id="email"
                                type="email"
                                className="mt-1 block w-full"
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                            />
                            <InputError message={form.errors.email} />
                        </div>
                    </div>
                    <label className="flex items-center gap-2">
                        <Checkbox
                            checked={form.data.is_admin}
                            onChange={(e) => form.setData('is_admin', e.target.checked)}
                        />
                        Administrateur
                    </label>
                    <div>
                        <InputLabel value="Sociétés" />
                        <div className="mt-1 flex flex-wrap gap-3">
                            {societes.map((s) => (
                                <label key={s.id} className="flex items-center gap-1">
                                    <Checkbox
                                        checked={form.data.societes.includes(s.id)}
                                        onChange={() => toggle('societes', s.id)}
                                    />
                                    {s.code}
                                </label>
                            ))}
                        </div>
                    </div>
                    {!form.data.is_admin && (
                        <div>
                            <InputLabel value="Pages" />
                            <div className="mt-1 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                {assignables.map((p) => (
                                    <label key={p.key} className="flex items-center gap-1">
                                        <Checkbox
                                            checked={form.data.pages.includes(p.key)}
                                            onChange={() => toggle('pages', p.key)}
                                        />
                                        {p.label}
                                    </label>
                                ))}
                            </div>
                        </div>
                    )}
                    <div className="flex justify-end gap-2">
                        <SecondaryButton type="button" onClick={fermer}>
                            Annuler
                        </SecondaryButton>
                        <PrimaryButton disabled={form.processing}>Créer</PrimaryButton>
                    </div>
                </form>
            </Modal>
        </>
    );
}

export default function AdminIndex({
    utilisateurs,
    societes,
    modeles,
    pagesCatalogue,
    audit,
    connexions,
    imports,
}) {
    const flash = usePage().props.flash?.status;
    const [editing, setEditing] = useState(null);
    const [tab, setTab] = useState('audit');

    const action = (url) => router.post(url, {}, { preserveScroll: true });

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-100">Administration</h2>}
        >
            <Head title="Administration" />

            <div className="mx-auto max-w-7xl space-y-6 py-6 sm:px-6 lg:px-8">
                <AdminNav />
                {flash && (
                    <div className="banner-succes">
                        {flash}
                    </div>
                )}

                <PanelSection title="Comptes">
                    <div className="mb-4">
                        <CreateUser societes={societes} pagesCatalogue={pagesCatalogue} />
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                            <thead>
                                <tr className="text-left font-bold text-gray-700 dark:text-gray-300">
                                    <th className="px-3 py-2">Nom</th>
                                    <th className="px-3 py-2">E-mail</th>
                                    <th className="px-3 py-2">Rôle</th>
                                    <th className="px-3 py-2">Sociétés</th>
                                    <th className="px-3 py-2">État</th>
                                    <th className="px-3 py-2">Dernière connexion</th>
                                    <th className="px-3 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                {utilisateurs.map((u) => (
                                    <Fragment key={u.id}>
                                        <tr>
                                            <td className="px-3 py-2 font-medium">{u.name}</td>
                                            <td className="px-3 py-2">{u.email}</td>
                                            <td className="px-3 py-2">
                                                {u.is_admin ? 'Administrateur' : `${u.pages.length} page(s)`}
                                            </td>
                                            <td className="px-3 py-2">{u.societes.join(', ') || '—'}</td>
                                            <td className="px-3 py-2">
                                                {u.verrouille ? (
                                                    <span className="font-semibold text-violet-600 dark:text-violet-400">Verrouillé</span>
                                                ) : u.verrou_temporaire ? (
                                                    <span className="text-amber-600 dark:text-amber-400">Verrou temporaire</span>
                                                ) : (
                                                    <span className="text-green-700 dark:text-green-300">Actif</span>
                                                )}
                                                {u.must_change_password && (
                                                    <span className="ml-1 text-xs text-gray-500 dark:text-gray-400">(chg. MDP)</span>
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-gray-500 dark:text-gray-400">
                                                {u.last_login_at ?? '—'}
                                            </td>
                                            <td className="space-x-1 px-3 py-2 whitespace-nowrap">
                                                <SecondaryButton
                                                    onClick={() =>
                                                        setEditing(editing === u.id ? null : u.id)
                                                    }
                                                >
                                                    Accès
                                                </SecondaryButton>
                                                <SecondaryButton
                                                    onClick={() =>
                                                        action(route('admin.users.reset', u.id))
                                                    }
                                                >
                                                    Réinit. MDP
                                                </SecondaryButton>
                                                {u.verrouille ? (
                                                    <SecondaryButton
                                                        onClick={() =>
                                                            action(route('admin.users.unlock', u.id))
                                                        }
                                                    >
                                                        Déverrouiller
                                                    </SecondaryButton>
                                                ) : (
                                                    <DangerButton
                                                        onClick={() =>
                                                            action(route('admin.users.lock', u.id))
                                                        }
                                                    >
                                                        Verrouiller
                                                    </DangerButton>
                                                )}
                                            </td>
                                        </tr>
                                        {editing === u.id && (
                                            <tr>
                                                <td colSpan={7} className="px-3 pb-3">
                                                    <AccessEditor
                                                        user={u}
                                                        societes={societes}
                                                        modeles={modeles}
                                                        pagesCatalogue={pagesCatalogue}
                                                        onDone={() => setEditing(null)}
                                                    />
                                                </td>
                                            </tr>
                                        )}
                                    </Fragment>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </PanelSection>

                <PanelSection title="Journaux">
                    <div className="mb-3 flex gap-2 text-sm">
                        {[
                            ['audit', "Journal d'audit"],
                            ['connexions', 'Connexions'],
                            ['imports', 'Imports'],
                        ].map(([k, label]) => (
                            <button
                                key={k}
                                onClick={() => setTab(k)}
                                className={`rounded px-3 py-1 ${
                                    tab === k ? 'bg-gray-800 dark:bg-gray-600 text-white' : 'bg-gray-100 dark:bg-gray-800'
                                }`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>

                    <div className="overflow-x-auto text-sm">
                        {tab === 'audit' && (
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                                <thead>
                                    <tr className="text-left font-bold text-gray-700 dark:text-gray-300">
                                        <th className="px-3 py-2">Quand</th>
                                        <th className="px-3 py-2">Qui</th>
                                        <th className="px-3 py-2">Action</th>
                                        <th className="px-3 py-2">Description</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {audit.map((a) => (
                                        <tr key={a.id}>
                                            <td className="px-3 py-2 text-gray-500 dark:text-gray-400">{a.created_at}</td>
                                            <td className="px-3 py-2">{a.acteur_libelle}</td>
                                            <td className="px-3 py-2 font-mono text-xs">{a.action}</td>
                                            <td className="px-3 py-2">{a.description}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}

                        {tab === 'connexions' && (
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                                <thead>
                                    <tr className="text-left font-bold text-gray-700 dark:text-gray-300">
                                        <th className="px-3 py-2">Quand</th>
                                        <th className="px-3 py-2">E-mail</th>
                                        <th className="px-3 py-2">Résultat</th>
                                        <th className="px-3 py-2">IP</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {connexions.map((c) => (
                                        <tr key={c.id}>
                                            <td className="px-3 py-2 text-gray-500 dark:text-gray-400">{c.created_at}</td>
                                            <td className="px-3 py-2">{c.email}</td>
                                            <td className="px-3 py-2">
                                                {c.successful ? (
                                                    <span className="text-green-700 dark:text-green-300">réussie</span>
                                                ) : (
                                                    <span className="text-violet-600 dark:text-violet-400">
                                                        échec ({c.motif_echec})
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-gray-500 dark:text-gray-400">{c.ip}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}

                        {tab === 'imports' && (
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                                <thead>
                                    <tr className="text-left font-bold text-gray-700 dark:text-gray-300">
                                        <th className="px-3 py-2">Quand</th>
                                        <th className="px-3 py-2">Fichier</th>
                                        <th className="px-3 py-2">Statut</th>
                                        <th className="px-3 py-2">Lignes</th>
                                        <th className="px-3 py-2">Nouv. / Rempl.</th>
                                        <th className="px-3 py-2">Anomalies</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {imports.map((b) => (
                                        <tr key={b.id}>
                                            <td className="px-3 py-2 text-gray-500 dark:text-gray-400">{b.created_at}</td>
                                            <td className="px-3 py-2">{b.original_filename}</td>
                                            <td className="px-3 py-2">
                                                {b.status}
                                                {b.error_message && (
                                                    <span className="block text-xs text-violet-600 dark:text-violet-400">
                                                        {b.error_message}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-3 py-2">{b.rows_total}</td>
                                            <td className="px-3 py-2">
                                                {b.commandes_new} / {b.commandes_replaced}
                                            </td>
                                            <td className="px-3 py-2">{b.anomalies_count}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </PanelSection>
            </div>
        </AuthenticatedLayout>
    );
}
