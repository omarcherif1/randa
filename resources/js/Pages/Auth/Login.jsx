import randaLogo from '@/assets/logo.png';
import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, EyeOff, Lock, Mail, PackageCheck, TrendingUp, Truck } from 'lucide-react';
import { useState } from 'react';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });
    const [motDePasseVisible, setMotDePasseVisible] = useState(false);

    const soumettre = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    const erreur = errors.email || errors.password;

    return (
        <div
            style={{
                minHeight: '100vh',
                display: 'flex',
                fontFamily: 'Figtree, Inter, system-ui, sans-serif',
                background: '#F4F6FA',
                padding: 24,
                boxSizing: 'border-box',
                // Page volontairement claire uniquement : sans ça, un système en
                // mode sombre fait rendre les <input> natifs en texte blanc par
                // défaut (fond transparent + aucune couleur explicite) — invisible
                // sur ce fond clair.
                colorScheme: 'light',
            }}
        >
            <Head title="Connexion" />

            <div
                style={{
                    margin: 'auto',
                    width: '100%',
                    maxWidth: 980,
                    background: '#fff',
                    borderRadius: 20,
                    border: '1px solid #E7EAF0',
                    boxShadow: '0 20px 50px rgba(20,30,60,0.08)',
                    display: 'flex',
                    overflow: 'hidden',
                    minHeight: 560,
                }}
                className="rd-login-card"
            >
                {/* Panneau illustré */}
                <div
                    className="rd-login-illustration"
                    style={{
                        flex: 1,
                        minWidth: 0,
                        padding: 40,
                        display: 'flex',
                        flexDirection: 'column',
                        justifyContent: 'space-between',
                        background: 'linear-gradient(155deg, #1F3FA6 0%, #2952CC 55%, #4A76E0 100%)',
                        color: '#fff',
                        position: 'relative',
                    }}
                >
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                        <div
                            style={{
                                width: 34,
                                height: 34,
                                borderRadius: 8,
                                background: 'rgba(255,255,255,0.92)',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                overflow: 'hidden',
                            }}
                        >
                            <img src={randaLogo} alt="" style={{ width: '100%', height: '100%', objectFit: 'contain' }} />
                        </div>
                        <span style={{ fontSize: 14, fontWeight: 600 }}>Groupe Randa</span>
                    </div>

                    <div>
                        <div style={{ display: 'flex', gap: 10, marginBottom: 22 }}>
                            {[TrendingUp, PackageCheck, Truck].map((Icon, i) => (
                                <div
                                    key={i}
                                    style={{
                                        width: 42,
                                        height: 42,
                                        borderRadius: 10,
                                        background: 'rgba(255,255,255,0.12)',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                    }}
                                >
                                    <Icon size={20} />
                                </div>
                            ))}
                        </div>
                        <h2 style={{ fontSize: 26, fontWeight: 700, margin: '0 0 12px', lineHeight: 1.25 }}>
                            Pilotage logistique &amp; satisfaction client
                        </h2>
                        <p style={{ fontSize: 14, opacity: 0.85, lineHeight: 1.6, maxWidth: 340 }}>
                            Suivi en temps réel des livraisons, des ruptures de stock et des
                            prévisions de production pour Randa, SMT et Tunisie Farine.
                        </p>
                    </div>

                    <div style={{ fontSize: 11.5, opacity: 0.6 }}>© 2026 Groupe Randa. Tous droits réservés.</div>
                </div>

                {/* Formulaire */}
                <div
                    className="rd-login-form"
                    style={{ flex: 1, minWidth: 0, padding: '48px 56px', display: 'flex', flexDirection: 'column', justifyContent: 'center' }}
                >
                    <h1 style={{ fontSize: 23, fontWeight: 700, margin: '0 0 6px' }}>Bon retour</h1>
                    <p style={{ fontSize: 13.5, color: '#6B7385', margin: '0 0 28px' }}>
                        Entrez vos identifiants pour accéder à l'espace de pilotage.
                    </p>

                    {status && (
                        <div
                            style={{
                                fontSize: 12.5,
                                color: '#1F6B3B',
                                background: '#EAF7EF',
                                borderRadius: 8,
                                padding: '9px 12px',
                                marginBottom: 16,
                            }}
                        >
                            {status}
                        </div>
                    )}

                    <form onSubmit={soumettre}>
                        <label htmlFor="email" style={{ fontSize: 12.5, fontWeight: 600, color: '#4A5468', display: 'block', marginBottom: 6 }}>
                            Adresse e-mail
                        </label>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                                border: '1px solid #E1E5EC',
                                borderRadius: 8,
                                padding: '10px 12px',
                                marginBottom: 16,
                            }}
                        >
                            <Mail size={16} color="#8A93A6" />
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                required
                                autoFocus
                                autoComplete="username"
                                placeholder="prenom.nom@randa.local"
                                style={{ border: 'none', outline: 'none', fontSize: 13.5, width: '100%', background: 'transparent', color: '#0F172A' }}
                            />
                        </div>

                        <label htmlFor="password" style={{ fontSize: 12.5, fontWeight: 600, color: '#4A5468', display: 'block', marginBottom: 6 }}>
                            Mot de passe
                        </label>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                                border: '1px solid #E1E5EC',
                                borderRadius: 8,
                                padding: '10px 12px',
                                marginBottom: 12,
                            }}
                        >
                            <Lock size={16} color="#8A93A6" />
                            <input
                                id="password"
                                type={motDePasseVisible ? 'text' : 'password'}
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                required
                                autoComplete="current-password"
                                placeholder="••••••••"
                                style={{ border: 'none', outline: 'none', fontSize: 13.5, width: '100%', background: 'transparent', color: '#0F172A' }}
                            />
                            <button
                                type="button"
                                onClick={() => setMotDePasseVisible((v) => !v)}
                                aria-label={motDePasseVisible ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
                                style={{ display: 'flex', alignItems: 'center', background: 'transparent', border: 'none', cursor: 'pointer', padding: 0, color: '#8A93A6' }}
                            >
                                {motDePasseVisible ? <EyeOff size={16} /> : <Eye size={16} />}
                            </button>
                        </div>

                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
                            <label style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12.5, color: '#4A5468', cursor: 'pointer' }}>
                                <input
                                    type="checkbox"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                />
                                Se souvenir de moi
                            </label>
                            {canResetPassword && (
                                <Link
                                    href={route('password.request')}
                                    style={{ fontSize: 12.5, color: '#2952CC', fontWeight: 500 }}
                                >
                                    Mot de passe oublié ?
                                </Link>
                            )}
                        </div>

                        {erreur && (
                            <div style={{ fontSize: 12.5, color: '#791F1F', background: '#FCEBEB', borderRadius: 8, padding: '9px 12px', marginBottom: 16 }}>
                                {erreur}
                            </div>
                        )}

                        <button
                            type="submit"
                            disabled={processing}
                            style={{
                                width: '100%',
                                padding: '11px',
                                borderRadius: 8,
                                border: 'none',
                                background: '#2952CC',
                                color: '#fff',
                                fontWeight: 600,
                                fontSize: 13.5,
                                cursor: processing ? 'default' : 'pointer',
                                opacity: processing ? 0.7 : 1,
                            }}
                        >
                            {processing ? 'Connexion…' : 'Se connecter'}
                        </button>
                    </form>

                    <p style={{ marginTop: 22, fontSize: 11.5, color: '#8A93A6', textAlign: 'center' }}>
                        Accès réservé au personnel du Groupe Randa — un compte se crée par un
                        administrateur.
                    </p>
                </div>
            </div>
        </div>
    );
}
