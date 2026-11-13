/** The PITON mark: a bold "P" with a flag notch, drawn with the current text color. */
export default function AppLogoIcon(props) {
    return (
        <svg {...props} viewBox="0 0 40 42" xmlns="http://www.w3.org/2000/svg">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M7 3h15.5C30 3 35 8 35 14.5S30 26 22.5 26H15v13H7V3Zm8 7v9h7c2.8 0 5-2 5-4.5S24.8 10 22 10h-7Z"
            />
            <path d="M28 30h7l-3 3.5 3 3.5h-7v-7Z" />
        </svg>
    );
}
