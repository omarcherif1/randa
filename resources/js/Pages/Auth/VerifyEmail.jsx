import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Vérification de l'e-mail" />

            <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                Vérifiez votre e-mail
            </h2>
            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Cliquez sur le lien reçu par e-mail pour confirmer votre adresse. Rien
                reçu ? Nous pouvons en renvoyer un.
            </p>

            {status === 'verification-link-sent' && (
                <p className="banner-succes mt-6">
                    Un nouveau lien de vérification a été envoyé à votre adresse e-mail.
                </p>
            )}

            <form onSubmit={submit} className="mt-6 flex items-center justify-between">
                <PrimaryButton disabled={processing}>Renvoyer l'e-mail</PrimaryButton>

                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="text-sm text-gray-600 underline hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    Se déconnecter
                </Link>
            </form>
        </GuestLayout>
    );
}
