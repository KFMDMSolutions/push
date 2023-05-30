const registration = await navigator.serviceWorker.register('./firebase-messaging-sw.js')

const messaging = firebase.messaging();

function requestPermission() {
	Notification.requestPermission().then((permission) => {
		if (permission === 'granted') {
			messaging.getToken( {
				serviceWorkerRegistration: registration,
				vapidKey: vapidKey })
			.then((token) =>{
				if(token){
					if(verifiedUser()){
						$.post(firebase_user_url, {
							"user_id": verifiedUser(),
							"firebase_token": token
						}).then(function(data){
							console.log(data);
						});
					}else{
						console.log('You are not Logged in');
					}
				} else {
					// Show permission request UI
					console.log('No registration token available. Request permission to generate one.');
				}
			}).catch((err)=> {
				//console.log('Unable to get permission to notify.', err);
			});
			
			messaging.onMessage((payload)=> {	
				if(verifiedUser() == payload.data.user_id){
					console.log('onMessage', payload);
					const notificationTitle = payload.data.title;
					const notificationOptions = {
						body: payload.data.body,
						icon: payload.data.icon,
						dir: payload.data.dir,
						data: {
							url: payload.data.url,
						},
					};
					navigator.serviceWorker.ready.then((registration1) => {
						registration1.showNotification(notificationTitle, notificationOptions);
					});
				}else{
					console.log('You are not Logged in');
				}
			});
		}
	});
}

function unsubscribe() {
	if (Notification.permission == "granted") {
		messaging.getToken( {
				serviceWorkerRegistration: registration,
				vapidKey: vapidKey })
		.then((token) =>{
			if(token){
				if(verifiedUser()){
					console.log(token);

					$.post(firebase_unsubscribe_url, {
					  "user_id": verifiedUser(),
					  "firebase_token": token
					}).then(function(data){
					  console.log(data);
					});
				}
			} else {
				// Show permission request UI
				console.log('No registration token available. Request permission to generate one.');
			}
		}).catch((err)=> {
			//console.log('Unable to get permission to notify.', err);
		});	
	}
}

if ('serviceWorker' in navigator){
	requestPermission()
}

function verifiedUser(){
    var response = $.ajax({
		url:   firebase_verify_user_url,
		async: false,
		dataType: 'json',
		success: function (json) {
			response = json;
		},
		type:  'get'
	});    
    return response.responseJSON;
}



	
