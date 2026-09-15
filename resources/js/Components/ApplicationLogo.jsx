import logo from '@/assets/logo.png';

export default function ApplicationLogo({ className = '', ...props }) {
    return (
        <img
            src={logo}
            alt="Groupe Randa"
            className={`object-contain ${className}`}
            {...props}
        />
    );
}
