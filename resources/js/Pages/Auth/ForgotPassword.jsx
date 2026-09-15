import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Mot de passe oublié" />

            <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Mot de passe oublié</h2>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Indiquez votre adresse e-mail : nous vous envoyons un lien pour choisir un
                nouveau mot de passe.
            </p>

            {status && <p className="banner-succes mt-6">{status}</p>}

            <form onSubmit={submit} className="mt-6 space-y-5">
                <TextInput
                    id="email"
                    type="email"
                    name="email"
                    value={data.email}
                    className="block w-full px-3.5 py-2.5"
                    isFocused={true}
                    onChange={(e) => setData('email', e.target.value)}
                    placeholder="prenom.nom@randa.local"
                />
                <InputError message={errors.email} />

                <PrimaryButton className="w-full justify-center py-2.5" disabled={processing}>
                    Envoyer le lien de réinitialisation
                </PrimaryButton>
            </form>
        </GuestLayout>
    );
}
