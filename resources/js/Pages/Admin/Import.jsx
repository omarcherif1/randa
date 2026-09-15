import AdminNav from '@/Components/admin/AdminNav';
import PanelSection from '@/Components/PanelSection';
import DangerButton from '@/Components/DangerButton';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { dateHeureFr, nInt } from '@/Components/dashboard/format';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

const TYPES = [
    { valeur: 'unified', label: 'Fichier unifié quotidien' },
    { valeur: 'stock', label: 'Fichier stock' },
];

const STATUTS = {
    completed: { label: 'Terminé', classe: 'text-green-700 dark:text-green-300' },
    running: { label: 'En cours', classe: 'text-amber-600 dark:text-amber-400' },
    failed: { label: 'Échoué', classe: 'font-semibold text-violet-600 dark:text-violet-400' },
};

export default function Import({ imports }) {
    const flash = usePage().props.flash;
    const fileInput = useRef(null);
    const { data, setData, post, processing, progress, errors, reset } = useForm({
        fichier: null,
        type: 'unified',
    });
    const [aSupprimer, setASupprimer] = useState(null);
    const [suppression, setSuppression] = useState(false);

    const soumettre = (e) => {
        e.preventDefault();
        post(route('admin.import.store'), {
            forceFormData: true,
            onSuccess: () => {
                reset('fichier');
                if (fileInput.current) fileInput.current.value = '';
            },
        });
    };

    const confirmerSuppression = () => {
        setSuppression(true);
        router.delete(route('admin.import.destroy', aSupprimer.id), {
            preserveScroll: true,
            onFinish: () => {
                setSuppression(false);
                setASupprimer(null);
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-100">Administration</h2>}
        >
            <Head title="Import de données" />

            <div className="mx-auto max-w-7xl space-y-6 py-6 sm:px-6 lg:px-8">
                <AdminNav />

                {flash?.status && <div className="banner-succes">{flash.status}</div>}
                {flash?.erreur && <div className="banner-critique">{flash.erreur}</div>}

                <PanelSection title="Nouvel import">
                    <p className="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Le fichier remplace les commandes qu'il mentionne (dédoublonnage par
                        numéro de commande, règle 4.7) — il ne s'ajoute jamais aux données
                        existantes. Les indicateurs de toute l'application sont recalculés
                        automatiquement à la fin de l'import.
                    </p>

                    <form onSubmit={soumettre} className="space-y-4">
                        <div>
                            <InputLabel value="Type de fichier" />
                            <div className="mt-1 flex flex-wrap gap-3">
                                {TYPES.map((t) => (
                                    <label key={t.valeur} className="flex items-center gap-1.5 text-sm">
                                        <input
                                            type="radio"
                                            name="type"
                                            value={t.valeur}
                                            checked={data.type === t.valeur}
                                            onChange={(e) => setData('type', e.target.value)}
                                            className="text-indigo-600 focus:ring-indigo-500"
                                        />
                                        {t.label}
                                    </label>
                                ))}
                            </div>
                        </div>

                        <div>
                            <InputLabel htmlFor="fichier" value="Fichier (.csv)" />
                            <input
                                id="fichier"
                                ref={fileInput}
                                type="file"
                                accept=".csv,.txt"
                                onChange={(e) => setData('fichier', e.target.files[0] ?? null)}
                                className="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-gray-700 hover:file:bg-gray-200 dark:text-gray-400 dark:file:bg-gray-800 dark:file:text-gray-300 dark:hover:file:bg-gray-700"
                            />
                            {errors.fichier && (
                                <p className="mt-1 text-sm text-red-600 dark:text-red-400">{errors.fichier}</p>
                            )}
                        </div>

                        {progress && (
                            <div className="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                <div
                                    className="h-full bg-gray-800 dark:bg-gray-400"
                                    style={{ width: `${progress.percentage}%` }}
                                />
                            </div>
                        )}

                        <PrimaryButton disabled={processing || !data.fichier}>
                            {processing ? 'Import en cours…' : 'Importer'}
                        </PrimaryButton>
                    </form>
                </PanelSection>

                <PanelSection title="Imports récents">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                            <thead>
                                <tr className="text-left font-bold text-gray-700 dark:text-gray-300">
                                    <th className="px-3 py-2">Quand</th>
                                    <th className="px-3 py-2">Fichier</th>
                                    <th className="px-3 py-2">Type</th>
                                    <th className="px-3 py-2">Statut</th>
                                    <th className="px-3 py-2">Lignes</th>
                                    <th className="px-3 py-2">Nouv. / Rempl.</th>
                                    <th className="px-3 py-2">Anomalies</th>
                                    <th className="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                {imports.length === 0 && (
                                    <tr>
                                        <td className="px-3 py-4 text-gray-500 dark:text-gray-400" colSpan={8}>
                                            Aucun import pour l'instant.
                                        </td>
                                    </tr>
                                )}
                                {imports.map((b) => {
                                    const s = STATUTS[b.status] ?? { label: b.status, classe: 'text-gray-500' };
                                    return (
                                        <tr key={b.id}>
                                            <td className="px-3 py-2 text-gray-500 dark:text-gray-400">
                                                {dateHeureFr(b.created_at)}
                                            </td>
                                            <td className="px-3 py-2">{b.original_filename}</td>
                                            <td className="px-3 py-2">
                                                {b.type === 'unified' ? 'Unifié' : 'Stock'}
                                            </td>
                                            <td className={`px-3 py-2 ${s.classe}`}>
                                                {s.label}
                                                {b.error_message && (
                                                    <span className="block text-xs text-violet-600 dark:text-violet-400">
                                                        {b.error_message}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-3 py-2">{nInt(b.rows_imported)} / {nInt(b.rows_total)}</td>
                                            <td className="px-3 py-2">
                                                {nInt(b.commandes_new)} / {nInt(b.commandes_replaced)}
                                            </td>
                                            <td className="px-3 py-2">{nInt(b.anomalies_count)}</td>
                                            <td className="px-3 py-2 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => setASupprimer(b)}
                                                    className="text-xs text-violet-600 hover:underline dark:text-violet-400"
                                                >
                                                    Supprimer
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </PanelSection>
            </div>

            <Modal show={aSupprimer !== null} onClose={() => setASupprimer(null)}>
                <div className="p-6">
                    <h2 className="font-semibold text-gray-800 dark:text-gray-100">
                        Retirer cet import de la liste ?
                    </h2>
                    <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        « {aSupprimer?.original_filename} » sera retiré de l'historique des imports.
                        Les commandes déjà importées restent inchangées — seule cette ligne du
                        journal disparaît.
                    </p>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton onClick={() => setASupprimer(null)} disabled={suppression}>
                            Annuler
                        </SecondaryButton>
                        <DangerButton onClick={confirmerSuppression} disabled={suppression}>
                            {suppression ? 'Suppression…' : 'Supprimer'}
                        </DangerButton>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
