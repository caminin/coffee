import { useState, useEffect, useRef } from 'react';
import { MERCURE_HUB_URL } from '../services/apiConfig';

export const useMercureEvents = (knownMachineStatusKeys) => {
  const [mercureMachineStatus, setMercureMachineStatus] = useState(null);
  const [newEvent, setNewEvent] = useState(null);
  const [updatedEvent, setUpdatedEvent] = useState(null);
  const [deletedEvent, setDeletedEvent] = useState(null);
  const [finishedEvent, setFinishedEvent] = useState(null);
  const [cancelledEvent, setCancelledEvent] = useState(null);
  
  const [mercureError, setMercureError] = useState(null);
  const [isReconnecting, setIsReconnecting] = useState(false);
  const eventSourceRef = useRef(null);
  const reconnectTimeoutIdRef = useRef(null);

  useEffect(() => {
    const connect = () => {
      if (eventSourceRef.current) {
        eventSourceRef.current.close();
      }
      setIsReconnecting(false);
      setMercureError(null);

      const url = new URL(MERCURE_HUB_URL);
      url.searchParams.append('topic', '/worker/status');
      url.searchParams.append('topic', '/commandes');

      const es = new EventSource(url);
      eventSourceRef.current = es;

      es.onopen = () => {
        console.log('Connexion Mercure établie via useMercureEvents.');
        setMercureError(null);
        setIsReconnecting(false);
      };

      es.onmessage = (event) => {
        console.log('Message Mercure générique (hook):', event.data);
        // Peut-être gérer des heartbeats ici si nécessaire
      };

      es.addEventListener('worker_status_updated', (event) => {
        console.log('Hook Mercure event: worker_status_updated', event.data);
        try {
          const data = JSON.parse(event.data);
          if (data && typeof data.status === 'string') {
            const statusKey = data.status.toLowerCase();
            if (knownMachineStatusKeys.includes(statusKey)) {
              console.log(`%cHook: setting machineStatus to known key: '${statusKey}' from event:`, 'color: blue;', event.data);
              setMercureMachineStatus(statusKey);
            } else {
              console.warn(`Hook: received unknown machineStatus key: '${statusKey}' from event:`, event.data);
              setMercureMachineStatus('unknown'); // Fallback
            }
          } else {
            console.warn('Mercure event worker_status_updated (hook) reçu sans data.status valide ou non-string:', data);
          }
        } catch (e) {
          console.error('Erreur parsing JSON pour worker_status_updated (hook):', e);
        }
      });

      const createEventSetter = (setterFunction, eventName) => (event) => {
        console.log(`Hook Mercure event: ${eventName}`, event.data);
        try {
          const data = JSON.parse(event.data);
          setterFunction({ type: eventName, data });
        } catch (e) {
          console.error(`Erreur parsing JSON pour ${eventName} (hook):`, e);
        }
      };

      es.addEventListener('commande_creee', createEventSetter(setNewEvent, 'commande_creee'));
      es.addEventListener('commande_en_cours', createEventSetter(setUpdatedEvent, 'commande_en_cours'));
      es.addEventListener('commande_updated', createEventSetter(setUpdatedEvent, 'commande_updated'));
      es.addEventListener('commande_deleted', createEventSetter(setDeletedEvent, 'commande_deleted'));
      es.addEventListener('commande_terminee', createEventSetter(setFinishedEvent, 'commande_terminee'));
      es.addEventListener('commande_annulee', createEventSetter(setCancelledEvent, 'commande_annulee'));

      es.onerror = (error) => {
        console.error("Erreur EventSource Mercure (hook):", error);
        setMercureError('Connexion temps réel perdue. Tentative de reconnexion...');
        setIsReconnecting(true);
        
        if (eventSourceRef.current) {
          eventSourceRef.current.close();
        }

        clearTimeout(reconnectTimeoutIdRef.current);
        reconnectTimeoutIdRef.current = setTimeout(() => {
          console.log('Tentative de reconnexion à Mercure (hook)...');
          connect();
        }, 5000);
      };
    };

    connect();

    return () => {
      clearTimeout(reconnectTimeoutIdRef.current);
      if (eventSourceRef.current) {
        eventSourceRef.current.close();
      }
    };
  }, [knownMachineStatusKeys]);

  return {
    mercureMachineStatus,
    newEvent,
    updatedEvent,
    deletedEvent,
    finishedEvent,
    cancelledEvent,
    mercureError,
    isReconnecting,
  };
}; 