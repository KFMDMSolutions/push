// import { initializeApp } from "https://www.gstatic.com/firebasejs/9.16.0/firebase-app.js";
// import { getMessaging,getToken,onMessage  } from "https://www.gstatic.com/firebasejs/9.16.0/firebase-messaging.js";
// import { getAnalytics } from "https://www.gstatic.com/firebasejs/9.16.0/firebase-analytics.js";

 
// // TODO: Add SDKs for Firebase products that you want to use
// // https://firebase.google.com/docs/web/setup#available-libraries

// // Your web app's Firebase configuration
// // For Firebase JS SDK v7.20.0 and later, measurementId is optional

// // Initialize Firebase
// const app = initializeApp(firebaseConfig);
// const analytics = getAnalytics(app);
// const messaging = getMessaging(app);

const registration = await navigator.serviceWorker.register('./firebase-messaging-sw.js')
const messaging = firebase.messaging();
// Perhaps move this into an onclick on a button
// so that permission is only requested when the user wants to
// enable it, not just when they view the site for the first time
function requestPermission() {
	Notification.requestPermission().then((permission) => {
		if (permission === 'granted') {
			//console.log('Notification permission granted.');
			messaging.getToken( {
				serviceWorkerRegistration: registration,
				vapidKey: vapidKey })
			.then((token) =>{
				if(token){
					console.log(token);
					// Send an AJAX call to the controller
					// to add the user_id and this token
					// This way when the user needs to be notified
					// we just gather all of the tokens associated with this user's id
					// and send it
					$.post(firebase_user_url, {
					  "user_id": firebase_phpbb_user_id,
					  "firebase_token": token
					}).then(function(data){
					  console.log(data);
					});
				} else {
					// Show permission request UI
					console.log('No registration token available. Request permission to generate one.');
				}
			}).catch((err)=> {
				//console.log('Unable to get permission to notify.', err);
			});

			messaging.onMessage((payload)=> {
			  // If the user is already on the page
			  // don't display a traditional notification
			  // do something else, maybe?
			  console.log('onMessage', payload);
			});
		}
	});
}
requestPermission()

  // const notificationTitle = 'Background Message Title';
  // const notificationOptions = {
    // body: 'Background Message body.',
    // icon: '/firebase-logo.png'
  // };

  // self.registration.showNotification(notificationTitle,
    // notificationOptions);