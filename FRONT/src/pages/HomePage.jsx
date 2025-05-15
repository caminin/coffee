import React, { useState, useEffect, useCallback } from 'react';
import './HomePage.css';
import OrderHistoryPanel from '../components/OrderHistoryPanel';
import MainControlPanel from '../components/MainControlPanel';
import PendingOrdersPanel from '../components/PendingOrdersPanel';
import AppLogo from '../assets/icon.svg'; // Importer le logo
import {
  getInitialOrderHistory,
  createCoffeeOrder,
  cancelPendingOrder,
  stopProcessingOrder
} from '../services/graphqlService';
import { getMachineStatus, controlMachine } from '../services/machineService';
import { useMercureEvents } from '../hooks/useMercureEvents';

const machineStatusTranslations = {
  unknown: 'Inconnu',
  stopped: 'Arrêtée',
  started: 'En marche',
  processing: 'En traitement',
  error: 'Erreur',
  restarting: 'Redémarrage',
};

const knownMachineStatusKeys = Object.keys(machineStatusTranslations);

const HomePage = () => {
  // État pour le formulaire de commande de café
  const [coffeeOrder, setCoffeeOrder] = useState({
    type: 'Espresso',
    intensite: 5,
    taille: 'Moyen',
  });

  // État pour la machine
  const [machineStatus, setMachineStatus] = useState('unknown');
  const [isMachineBusy, setIsMachineBusy] = useState(false);
  const [isOrderLoading, setIsOrderLoading] = useState(false);
  const [error, setError] = useState(null);
  const [currentOrders, setCurrentOrders] = useState([]);
  const [cancellingOrderId, setCancellingOrderId] = useState(null);
  const [orderHistory, setOrderHistory] = useState([]);

  // Utilisation du hook Mercure
  const {
    mercureMachineStatus: rawMachineStatusFromHook,
    newEvent: newOrderFromHook,
    updatedEvent: updatedOrderFromHook,
    deletedEvent: deletedOrderEventFromHook,
    finishedEvent: finishedOrderFromHook,
    cancelledEvent: cancelledOrderFromHook,
    mercureError: mercureErrorFromHook,
    isReconnecting: isMercureHookReconnecting,
  } = useMercureEvents(knownMachineStatusKeys);

  // Effet pour gérer les mises à jour du statut de la machine depuis le hook Mercure
  useEffect(() => {
    if (rawMachineStatusFromHook) {
      console.log(`%cHomePage: received status from hook: '${rawMachineStatusFromHook}'. Current local machineStatus: '${machineStatus}'. Setting to new status.`, 'color: green;');
      setMachineStatus(rawMachineStatusFromHook);
      setIsMachineBusy(false);
    }
  }, [rawMachineStatusFromHook]);

  // Effet pour gérer les nouvelles commandes depuis le hook Mercure
  useEffect(() => {
    if (newOrderFromHook && newOrderFromHook.type === 'commande_creee') {
      const newOrder = newOrderFromHook.data;
      if (newOrder && newOrder.id && newOrder.statut !== 'TERMINEE' && newOrder.statut !== 'ERREUR') {
        setCurrentOrders(prevOrders => {
          if (!prevOrders.find(order => order.id === newOrder.id)) {
            return [...prevOrders, newOrder];
          }
          return prevOrders;
        });
      }
    }
  }, [newOrderFromHook]);

  // Effet pour gérer les commandes mises à jour (updated, en_cours) depuis le hook Mercure
  useEffect(() => {
    if (updatedOrderFromHook && (updatedOrderFromHook.type === 'commande_updated' || updatedOrderFromHook.type === 'commande_en_cours')) {
      const updatedOrderData = updatedOrderFromHook.data;
      if (updatedOrderData && updatedOrderData.id) {
        setCurrentOrders(prevOrders =>
          prevOrders
            .map(order => (order.id === updatedOrderData.id ? { ...order, ...updatedOrderData } : order))
            .filter(order => order.statut !== 'TERMINEE' && order.statut !== 'ERREUR' && order.statut !== 'ANNULEE')
        );
      }
    }
  }, [updatedOrderFromHook]);

  // Effet pour gérer les commandes supprimées depuis le hook Mercure
  useEffect(() => {
    if (deletedOrderEventFromHook && deletedOrderEventFromHook.type === 'commande_deleted') {
      const eventData = deletedOrderEventFromHook.data;
      const commandeIdToDelete = eventData.commandeId || eventData.id;
      if (commandeIdToDelete) {
        setCurrentOrders(prevOrders =>
          prevOrders.filter(order => order.id.toString() !== commandeIdToDelete.toString())
        );
      }
    }
  }, [deletedOrderEventFromHook]);

  // Effet pour gérer les commandes terminées depuis le hook Mercure
  useEffect(() => {
    if (finishedOrderFromHook && finishedOrderFromHook.type === 'commande_terminee') {
      const finishedOrder = finishedOrderFromHook.data;
      if (finishedOrder && finishedOrder.id) {
        setOrderHistory(prevHistory => {
          const newHistory = [finishedOrder, ...prevHistory.filter(o => o.id !== finishedOrder.id)];
          return newHistory.slice(0, 10);
        });
        setCurrentOrders(prevOrders => prevOrders.filter(order => order.id !== finishedOrder.id));
      }
    }
  }, [finishedOrderFromHook]);

  // Effet pour gérer les commandes annulées depuis le hook Mercure
  useEffect(() => {
    if (cancelledOrderFromHook && cancelledOrderFromHook.type === 'commande_annulee') {
      const cancelledOrderData = cancelledOrderFromHook.data;
      if (cancelledOrderData && cancelledOrderData.id) {
        setOrderHistory(prevHistory => {
          const orderForHistory = { ...cancelledOrderData, statut: 'ANNULEE' };
          const newHistory = [orderForHistory, ...prevHistory.filter(o => o.id !== cancelledOrderData.id)];
          return newHistory.slice(0, 10);
        });
        setCurrentOrders(prevOrders => prevOrders.filter(order => order.id !== cancelledOrderData.id));
      }
    }
  }, [cancelledOrderFromHook]);
  
  // Effet pour gérer les erreurs Mercure et l'état de reconnexion depuis le hook
  useEffect(() => {
    if (mercureErrorFromHook) {
      setError(mercureErrorFromHook);
    } else {
      if (error && error.startsWith('Connexion temps réel perdue')) {
        setError(null); 
      }
    }
  }, [mercureErrorFromHook, error]);

  const fetchMachineStatusCallback = useCallback(async () => {
    setError(null);
    try {
      const status = await getMachineStatus();
      setMachineStatus(status);
    } catch (err) {
      console.error("Erreur fetchMachineStatusCallback:", err);
      setMachineStatus('error');
      setError(err.message);
    } finally {
      setIsMachineBusy(false);
    }
  }, []);

  const fetchInitialOrderHistoryCallback = useCallback(async () => {
    try {
      const historyData = await getInitialOrderHistory();
      console.log('Historique des commandes initial récupéré:', historyData);
      setOrderHistory(historyData);
    } catch (err) {
      console.error('Erreur fetchInitialOrderHistoryCallback:', err);
      setOrderHistory([]);
    }
  }, []);

  useEffect(() => {
    setIsMachineBusy(true);
    fetchMachineStatusCallback();
    fetchInitialOrderHistoryCallback();
  }, [fetchMachineStatusCallback, fetchInitialOrderHistoryCallback]);

  const handleCoffeeOrderChange = (e) => {
    const { name, value } = e.target;
    setCoffeeOrder(prev => ({ ...prev, [name]: name === 'intensite' ? parseInt(value, 10) : value }));
  };

  const handleOrderSubmit = async (e) => {
    e.preventDefault();
    setIsOrderLoading(true);
    setError(null);
    try {
      const newOrder = await createCoffeeOrder({
        type: coffeeOrder.type,
        intensite: coffeeOrder.intensite,
        taille: coffeeOrder.taille,
      });
      console.log('Commande créée:', newOrder);
      // La mise à jour de currentOrders est gérée par Mercure via le hook
    } catch (err) {
      console.error('Erreur handleOrderSubmit:', err);
      setError(err.message);
      alert(`Erreur commande: ${err.message}`);
    } finally {
      setIsOrderLoading(false);
    }
  };

  const callMachineApi = async (action) => {
    setIsMachineBusy(true); // Occupe la machine avant l'appel
    setError(null);
    try {
      await controlMachine(action);
      console.log(`Action API '${action}' envoyée. Attente de l'événement Mercure via hook.`);
    } catch (err) {
      console.error(`Erreur callMachineApi (${action}):`, err);
      setError(err.message);
      setMachineStatus('error');
      setIsMachineBusy(false); // Libérer en cas d'erreur API directe
      alert(`Erreur machine (${action}): ${err.message}`);
    }
  };

  const handleStartMachine = () => callMachineApi('start');
  const handleStopMachine = () => callMachineApi('stop');

  const handleRestartMachine = async () => {
    console.log('%cHomePage: handleRestartMachine CALLED', 'color: orange; font-weight: bold;');

    const ordersInProgress = currentOrders.filter(order => order.statut === 'EN_COURS');

    if (ordersInProgress.length > 0) {
      // Mettre à jour l'historique avec les commandes interrompues/annulées
      setOrderHistory(prevHistory => {
        let newHistoryEntries = [...prevHistory];
        ordersInProgress.forEach(orderToCancel => {
          const orderForHistory = { ...orderToCancel, statut: 'ANNULEE' };
          // Retirer l'ancienne version si elle existe, puis ajouter la nouvelle au début
          newHistoryEntries = [orderForHistory, ...newHistoryEntries.filter(o => o.id !== orderToCancel.id)];
        });
        return newHistoryEntries.slice(0, 10); // Garder les 10 plus récentes
      });

      // Retirer les commandes EN_COURS de la liste des commandes actuelles
      setCurrentOrders(prevOrders =>
        prevOrders.filter(order => order.statut !== 'EN_COURS')
      );
    }

    setMachineStatus('restarting');
    await callMachineApi('restart'); 
  };
  
  const isMachineRunning = machineStatus === 'started' || machineStatus === 'processing';
  const isMachineRestarting = machineStatus === 'restarting';

  const handleCancelPendingOrder = async (orderId) => {
    setCancellingOrderId(orderId);
    setError(null);
    try {
      const cancelledOrder = await cancelPendingOrder(orderId);
      console.log('Commande (en attente) annulée avec succès:', cancelledOrder);
      // La mise à jour de l'UI est gérée par Mercure
    } catch (err) {
      console.error('Erreur handleCancelPendingOrder:', err);
      setError(`Erreur annulation commande ${orderId}: ${err.message}`);
      alert(`Erreur lors de l'annulation de la commande ${orderId}: ${err.message}`);
    } finally {
      setCancellingOrderId(null);
    }
  };

  const handleStopProcessingOrder = async (orderId) => {
    setCancellingOrderId(orderId);
    setError(null);
    try {
      const stoppedOrder = await stopProcessingOrder(orderId);
      console.log('Commande (en cours) stoppée/annulée avec succès:', stoppedOrder);
    } catch (err) {
      console.error('Erreur handleStopProcessingOrder:', err);
      setError(`Erreur lors de l'arrêt/annulation de la commande ${orderId}: ${err.message}`);
      alert(`Erreur lors de l'arrêt/annulation de la commande ${orderId}: ${err.message}`);
    } finally {
      setCancellingOrderId(null);
    }
  };

  const pendingOrders = currentOrders.filter(order => order.statut === 'EN_ATTENTE');
  const processingOrders = currentOrders.filter(order => order.statut === 'EN_COURS');

  return (
    <div className="home-page">
      <header className="app-header-title">
        <img src={AppLogo} alt="Grain de Code Logo" />
        <h1>Grain de Code</h1>
      </header>

      <div className="panels-container" style={{ display: 'flex', width: '100%', gap: '20px' }}>
        <OrderHistoryPanel history={orderHistory} />
        <MainControlPanel 
          coffeeOrder={coffeeOrder}
          handleCoffeeOrderChange={handleCoffeeOrderChange}
          handleOrderSubmit={handleOrderSubmit}
          isOrderLoading={isOrderLoading}
          processingOrders={processingOrders}
          handleStopProcessingOrder={handleStopProcessingOrder}
          cancellingOrderId={cancellingOrderId}
          machineStatus={machineStatus}
          machineStatusTranslations={machineStatusTranslations}
          handleStartMachine={handleStartMachine}
          handleStopMachine={handleStopMachine}
          handleRestartMachine={handleRestartMachine}
          isMachineBusy={isMachineBusy}
          isMachineRunning={isMachineRunning}
          isMachineRestarting={isMachineRestarting}
        />
        <PendingOrdersPanel 
          pendingOrders={pendingOrders} 
          handleCancelOrder={handleCancelPendingOrder}
          cancellingOrderId={cancellingOrderId} 
        />
      </div>

      {error && (
        <div className="error-message global-error">
          {error}
          {isMercureHookReconnecting && <span className="reconnecting-indicator"> (Tentative de reconnexion...)</span>}
        </div>
      )}
    </div>
  );
};

export default HomePage; 