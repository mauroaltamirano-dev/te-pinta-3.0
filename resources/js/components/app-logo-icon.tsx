import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 48 48"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
        >
            <path
                d="M7 27C7 17.6112 14.6112 10 24 10C33.3888 10 41 17.6112 41 27H7Z"
                fill="currentColor"
            />
            <path
                d="M10 27C13.9 33.6 18.5667 37 24 37C29.4333 37 34.1 33.6 38 27"
                stroke="currentColor"
                strokeWidth="3"
                strokeLinecap="round"
            />
            <path
                d="M14 25L17 28M20 25L23 30M26 25L29 30M32 25L35 28"
                stroke="white"
                strokeWidth="2"
                strokeLinecap="round"
            />
        </svg>
    );
}
