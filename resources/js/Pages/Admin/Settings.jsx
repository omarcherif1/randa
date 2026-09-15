import AdminNav from '@/Components/admin/AdminNav';
import Checkbox from '@/Components/Checkbox';
import InputLabel from '@/Components/InputLabel';
import PanelSection from '@/Components/PanelSection';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

function Row({ setting }) {
    const form = useForm({ key: setting.key, value: setting.value ?? '' });

    const submit = (e) => {
        e.preventDefault();
        form.put(route('parametres.update'), { preserveScroll: true });
    };

    return (
        <form
            onSubmit={submit}
            className="flex flex-col gap-2 border-b border-gray-100 py-4 sm:flex-row sm:items-start sm:gap-6"
        >
            <div className="sm:w-1/2">
                <div className="font-medium text-gray-800 dark:text-gray-100">{setting.libelle}</div>
                <div className="text-sm text-gray-500 dark:text-gray-400">{setting.description}</div>
                <div className="font-mono text-xs text-gray-400 dark:text-gray-500">{setting.key}</div>
            </div>
            <div className="flex items-center gap-2">
                {setting.type === 'bool' ? (
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={!!form.data.value}
                            onChange={(e) => form.setData('value', e.target.checked)}
                        />
                        {form.data.value ? 'Activé' : 'Désactivé'}
                    </label>
                ) : (
                    <TextInput
                        className="w-48"
                        value={form.data.value ?? ''}
                        onChange={(e) => form.setData('value', e.target.value)}
                    />
                )}
                <PrimaryButton disabled={form.processing}>Enregistrer</PrimaryButton>
                {form.recentlySuccessful && <span className="text-sm text-green-700 dark:text-green-300">✓</span>}
            </div>
        </form>
    );
}

function AjoutMotCle({ categories }) {
    const form = useForm({ categorie_code: categories[0]?.code ?? '', keyword: '', priorite: 50 });
    const submit = (e) => {
        e.preventDefault();
        form.post(route('parametres.keywords.store'), {
            preserveScroll: true,
            onSuccess: () => form.setData('keyword', ''),
        });
    };

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-2 text-sm">
            <div>
                <InputLabel value="Catégorie" />
                <select
                    value={form.data.categorie_code}
                    onChange={(e) => form.setData('categorie_code', e.target.value)}
                    className="mt-1 rounded-md border-gray-300 dark:border-gray-700 text-sm"
                >
                    {categories.map((c) => (
                        <option key={c.id} value={c.code}>
                            {c.code} — {c.intitule}
                        </option>
                    ))}
                </select>
            </div>
            <div>
                <InputLabel value="Mot-clé" />
                <TextInput
                    className="mt-1 w-56"
                    value={form.data.keyword}
                    onChange={(e) => form.setData('keyword', e.target.value)}
                    placeholder="ex. CARREFOUR"
                />
            </div>
            <div>
                <InputLabel value="Priorité" />
                <TextInput
                    type="number"
                    min="0"
                    max="100"
                    className="mt-1 w-20"
                    value={form.data.priorite}
                    onChange={(e) => form.setData('priorite', e.target.value)}
                />
            </div>
            <PrimaryButton disabled={form.processing}>Ajouter</PrimaryButton>
            {form.errors.keyword && (
                <span className="text-sm text-red-600 dark:text-red-400">{form.errors.keyword}</span>
            )}
        </form>
    );
}

function MotCleLigne({ mot }) {
    const form = useForm({ priorite: mot.priorite, actif: mot.actif });
    const enregistrer = (patch = {}) => {
        const data = { ...form.data, ...patch };
        form.setData(data);
        router.put(route('parametres.keywords.update', mot.id), data, { preserveScroll: true });
    };
    const supprimer = () => {
        if (confirm(`Supprimer le mot-clé « ${mot.keyword} » ?`)) {
            router.delete(route('parametres.keywords.destroy', mot.id), { preserveScroll: true });
        }
    };

    return (
        <tr>
            <td className="px-3 py-2 font-medium">{mot.keyword}</td>
            <td className="px-3 py-2">
                <input
                    type="number"
                    min="0"
                    max="100"
                    value={form.data.priorite}
                    onChange={(e) => form.setData('priorite', e.target.value)}
                    onBlur={() => enregistrer()}
                    className="w-20 rounded border-gray-300 dark:border-gray-700 text-sm"
                />
            </td>
            <td className="px-3 py-2">
                <label className="flex items-center gap-1">
                    <Checkbox
                        checked={!!form.data.actif}
                        onChange={(e) => enregistrer({ actif: e.target.checked })}
                    />
                    {form.data.actif ? 'actif' : 'inactif'}
                </label>
            </td>
            <td className="px-3 py-2 text-right">
                <button onClick={supprimer} className="text-xs text-red-600 dark:text-red-400 hover:underline">
                    Supprimer
                </button>
            </td>
        </tr>
    );
}

export default function Settings({ settings, categories, motsCles }) {
    const flash = usePage().props.flash?.status;
    const [reclass, setReclass] = useState(false);

    const groupes = useMemo(() => {
        const m = new Map();
        for (const mot of motsCles) {
            const cle = mot.categorie ?? '—';
            if (!m.has(cle)) m.set(cle, []);
            m.get(cle).push(mot);
        }
        return [...m.entries()];
    }, [motsCles]);

    const reclasser = () => {
        setReclass(true);
        router.post(
            route('parametres.keywords.reclassify'),
            {},
            { preserveScroll: true, onFinish: () => setReclass(false) },
        );
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-100">Paramètres</h2>}
        >
            <Head title="Paramètres" />

            <div className="mx-auto max-w-7xl space-y-4 py-6 sm:px-6 lg:px-8">
                <AdminNav />
                {flash && (
                    <div className="banner-succes">{flash}</div>
                )}
                <div className="banner-attention">
                    Chaque modification est enregistrée dans le journal d'audit.
                </div>

                <PanelSection
                    title="Seuils réglables (règle métier 2, configurables, jamais réinterprétés)"
                    contentClassName="p-4 sm:p-6"
                >
                    {settings.map((s) => (
                        <Row key={s.key} setting={s} />
                    ))}
                </PanelSection>

                <PanelSection title="Mots-clés de classification client (règle 2.7)" contentClassName="p-4 sm:p-6">
                    <p className="mb-3 text-sm text-gray-500 dark:text-gray-400">
                        Un mot-clé (stocké en majuscules, sans accents) rattache un client à une
                        catégorie quand aucune source fiable ne l'a fait. Modifier la liste ne
                        reclasse pas automatiquement les clients : utilisez le bouton dédié.
                    </p>
                    <div className="mb-4 rounded-md border border-gray-200 dark:border-gray-800 p-3">
                        <AjoutMotCle categories={categories} />
                    </div>

                    {groupes.length === 0 && (
                        <p className="text-sm text-gray-500 dark:text-gray-400">Aucun mot-clé défini.</p>
                    )}
                    {groupes.map(([categorie, mots]) => (
                        <div key={categorie} className="mb-4">
                            <div className="text-sm font-semibold text-gray-700 dark:text-gray-300">{categorie}</div>
                            <table className="mt-1 min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                                <thead>
                                    <tr className="text-left text-xs font-bold text-gray-500 dark:text-gray-400">
                                        <th className="px-3 py-1">Mot-clé</th>
                                        <th className="px-3 py-1">Priorité</th>
                                        <th className="px-3 py-1">État</th>
                                        <th className="px-3 py-1"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {mots.map((mot) => (
                                        <MotCleLigne key={mot.id} mot={mot} />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ))}

                    <div className="mt-2 flex items-center gap-3">
                        <SecondaryButton onClick={reclasser} disabled={reclass}>
                            {reclass ? 'Reclassement…' : 'Reclasser les clients non tranchés'}
                        </SecondaryButton>
                        <span className="text-xs text-gray-500 dark:text-gray-400">
                            Ne touche que les clients « Indéfini » ou classés par mot-clé, jamais
                            ceux issus du référentiel ou déduits.
                        </span>
                    </div>
                </PanelSection>
            </div>
        </AuthenticatedLayout>
    );
}
