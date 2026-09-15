import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function ForcedPasswordChange() {
    const { data, setData, put, processing, errors, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('password.forced.update'), {
            onFinish: () => reset('current_password', 'password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Changer le mot de passe" />

            <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                Nouveau mot de passe requis
            </h2>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Pour votre sécurité, définissez un nouveau mot de passe avant de
                continuer. Minimum 10 caractères, avec lettres et chiffres.
            </p>

            <form onSubmit={submit} className="mt-6 space-y-5">
                <div>
                    <InputLabel htmlFor="current_password" value="Mot de passe actuel" />
                    <TextInput
                        id="current_password"
                        type="password"
                        name="current_password"
                        value={data.current_password}
                        className="mt-1.5 block w-full px-3.5 py-2.5"
                        autoComplete="current-password"
                        isFocused
                        onChange={(e) => setData('current_password', e.target.value)}
                    />
                    <InputError message={errors.current_password} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="password" value="Nouveau mot de passe" />
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1.5 block w-full px-3.5 py-2.5"
                        autoComplete="new-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="password_confirmation" value="Confirmer le mot de passe" />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className="mt-1.5 block w-full px-3.5 py-2.5"
                        autoComplete="new-password"
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />
                    <InputError message={errors.password_confirmation} className="mt-2" />
                </div>

                <PrimaryButton className="w-full justify-center py-2.5" disabled={processing}>
                    Enregistrer
                </PrimaryButton>
            </form>
        </GuestLayout>
    );
}
