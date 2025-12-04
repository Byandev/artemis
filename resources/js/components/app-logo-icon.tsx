import { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            {/* Arrow pointing up - representing Artemis logo's central arrow */}
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M12 2L4 10h5v10h6V10h5L12 2z"
            />
        </svg>
    );
}
