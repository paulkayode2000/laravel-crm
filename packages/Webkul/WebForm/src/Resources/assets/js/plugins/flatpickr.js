import Flatpickr from "flatpickr";
import "flatpickr/dist/flatpickr.css";

export default {
    install: (app) => {
        window.Flatpickr = Flatpickr;

        const changeTheme = (theme) => {
            document.getElementById('flatpickr')?.remove();

            if (theme === 'light') {
                return;
            }

            const linkElement = document.createElement("link");
            
            linkElement.rel = "stylesheet";
            linkElement.type = "text/css";
            // Themes are self-hosted in public/vendored/flatpickr/themes so that switching
            // to the dark theme never contacts npmcdn.com.
            linkElement.href = `/vendored/flatpickr/themes/${theme}.css`;
            linkElement.id = 'flatpickr';

            document.head.appendChild(linkElement);
        };

        const currentTheme = document.documentElement.classList.contains("dark")
            ? "dark"
            : "light";

        changeTheme(currentTheme);

        app.config.globalProperties.$emitter.on("change-theme", (theme) => {
            changeTheme(theme);
        });
    },
};
